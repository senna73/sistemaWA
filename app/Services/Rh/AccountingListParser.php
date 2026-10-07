<?php

namespace App\Services\Rh;

use App\Support\PersonName;
use App\Support\SimpleXlsx;
use Carbon\Carbon;
use InvalidArgumentException;

class AccountingListParser
{
    /**
     * @return list<array{code:string,name:string,admission_on:string}>
     */
    public function parse(string $path, ?string $originalName = null): array
    {
        $extension = strtolower(pathinfo($originalName ?: $path, PATHINFO_EXTENSION));
        $head = (string) @file_get_contents($path, false, null, 0, 8);

        if ($extension === 'pdf' || str_starts_with($head, '%PDF')) {
            return $this->fromText($this->pdfText($path));
        }

        if (in_array($extension, ['xlsx', 'xls'], true) || str_starts_with($head, 'PK') || str_starts_with($head, "\xD0\xCF\x11\xE0")) {
            return $this->fromGrid(SimpleXlsx::rows($path));
        }

        if ($extension === 'csv' || $extension === 'txt') {
            return $this->fromGrid($this->csv($path));
        }

        $text = @file_get_contents($path) ?: '';
        if ($this->looksLikeSci($text)) {
            return $this->fromText($text);
        }

        throw new InvalidArgumentException('Envie a lista da contabilidade em PDF (SCI), .xlsx ou .csv.');
    }

    /**
     * @return list<array{code:string,name:string,admission_on:string}>
     */
    public function fromText(string $text): array
    {
        $text = $this->normalizeExtractedText($text);
        $rows = [];
        $seen = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $this->pushRow($rows, $seen, $this->parseSciLine(trim($line)));
        }

        if ($rows === []) {
            foreach ($this->parseBlob($text) as $parsed) {
                $this->pushRow($rows, $seen, $parsed);
            }
        }

        if ($rows === []) {
            throw new InvalidArgumentException('Não encontrei código, nome e data de admissão na lista da contabilidade.');
        }

        return array_values($rows);
    }

    /**
     * @param  list<list<string>>  $grid
     * @return list<array{code:string,name:string,admission_on:string}>
     */
    public function fromGrid(array $grid): array
    {
        $headerAt = $this->headerIndex($grid);
        if ($headerAt !== null) {
            try {
                $map = $this->mapColumns($grid[$headerAt]);
                $rows = [];
                for ($i = $headerAt + 1; $i < count($grid); $i++) {
                    $line = $grid[$i];
                    $parsed = $this->normalize(
                        trim((string) ($line[$map['name']] ?? '')),
                        (string) ($line[$map['code']] ?? ''),
                        trim((string) ($line[$map['admission']] ?? '')),
                    );
                    if ($parsed !== null) {
                        $rows[] = $parsed;
                    }
                }
                if ($rows !== []) {
                    return $rows;
                }
            } catch (InvalidArgumentException) {
                // Cabeçalho incompleto: tenta o texto das células.
            }
        }

        $joined = [];
        foreach ($grid as $line) {
            $joined[] = implode(' ', $line);
        }

        return $this->fromText(implode("\n", $joined));
    }

    /**
     * @return array{code:string,name:string,admission_on:string}|null
     */
    public function parseSciLine(string $line): ?array
    {
        if ($line === '' || str_contains(mb_strtolower($line), 'total de colaboradores')) {
            return null;
        }

        $line = $this->normalizeExtractedText($line);

        if (preg_match('/(\d{2}\/\d{2}\/\d{2,4})\s*(.+?)(\d{6})\s*$/u', $line, $match)) {
            return $this->normalize(trim($match[2]), $match[3], $match[1]);
        }

        if (preg_match('/^(\d{1,6})\s+(.+?)\s+(\d{2}\/\d{2}\/\d{2,4})\s*$/u', $line, $match)) {
            return $this->normalize(trim($match[2]), $match[1], $match[3]);
        }

        if (preg_match('/^([A-Za-zÀ-ÿ].+?)\s+(\d{1,6})\s+(\d{2}\/\d{2}\/\d{2,4})\s*$/u', $line, $match)) {
            return $this->normalize(trim($match[1]), $match[2], $match[3]);
        }

        if (preg_match('/^([A-Za-zÀ-ÿ].+?)\s+(\d{2}\/\d{2}\/\d{2,4})\s+(\d{1,6})\s*$/u', $line, $match)) {
            return $this->normalize(trim($match[1]), $match[3], $match[2]);
        }

        if (preg_match('/(\d{2}\/\d{2}\/\d{2,4})\s+(\d{1,6})\s+(.+)$/u', $line, $match)) {
            return $this->normalize(trim($match[3]), $match[2], $match[1]);
        }

        return null;
    }

    /**
     * @return array{code:string,name:string,admission_on:string}|null
     */
    private function normalize(string $name, string $code, string $admission): ?array
    {
        $name = $this->cleanName($name);
        $digits = preg_replace('/\D/', '', $code) ?: '';
        if ($name === '' || PersonName::key($name) === '' || strlen($digits) < 1) {
            return null;
        }

        $admission = trim($admission);
        try {
            if (preg_match('/^\d{5}(\.\d+)?$/', $admission)) {
                $date = Carbon::create(1899, 12, 30)?->addDays((int) $admission);
            } elseif (preg_match('/^\d{2}\/\d{2}\/\d{2}$/', $admission)) {
                $date = Carbon::createFromFormat('d/m/y', $admission);
            } elseif (str_contains($admission, '/')) {
                $date = Carbon::createFromFormat('d/m/Y', $admission);
            } else {
                $date = Carbon::parse($admission);
            }
        } catch (\Throwable) {
            return null;
        }

        if (! $date) {
            return null;
        }

        return [
            'code' => str_pad(substr($digits, -6), 6, '0', STR_PAD_LEFT),
            'name' => $name,
            'admission_on' => $date->toDateString(),
        ];
    }

    public function pdfText(string $path): string
    {
        $data = file_get_contents($path);
        if ($data === false || $data === '') {
            throw new InvalidArgumentException('Não foi possível ler o PDF da contabilidade.');
        }

        $out = '';
        if (preg_match_all('/stream\r?\n(.*?)endstream/s', $data, $streams)) {
            foreach ($streams[1] as $stream) {
                $decoded = $this->inflate($stream);
                if ($decoded === null) {
                    continue;
                }
                $out .= $this->pdfOperators($decoded)."\n";
            }
        }

        if (trim($out) === '') {
            $out = $this->pdfOperators($data);
        }

        $out = preg_replace('/[^\P{C}\n]+/u', ' ', $out) ?? $out;

        return $this->normalizeExtractedText($out);
    }

    private function inflate(string $raw): ?string
    {
        $raw = preg_replace('/^\r?\n/', '', $raw) ?? $raw;
        foreach ([$raw, substr($raw, 2)] as $payload) {
            if (! is_string($payload) || $payload === '') {
                continue;
            }
            foreach (['gzuncompress', 'gzinflate'] as $fn) {
                $decoded = @$fn($payload);
                if (is_string($decoded) && $decoded !== '') {
                    return $decoded;
                }
            }
        }

        return null;
    }

    private function pdfOperators(string $decoded): string
    {
        $chunks = [];
        $offset = 0;
        $pattern = '/\((?:\\\\.|[^\\\\)])*\)\s*Tj|<([0-9A-Fa-f\s]+)>\s*Tj|\[(.*?)\]\s*TJ|T\*|\'/s';

        while (preg_match($pattern, $decoded, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $full = $match[0][0];
            $at = (int) $match[0][1];
            $offset = $at + strlen($full);

            if ($full === 'T*' || $full === "'") {
                $chunks[] = "\n";
                continue;
            }

            if (str_ends_with(rtrim($full), 'Tj') && str_starts_with($full, '(')) {
                if (preg_match('/^\((.*)\)\s*Tj/s', $full, $inner)) {
                    $chunks[] = $this->unescapePdf($inner[1]);
                }
                continue;
            }

            if (isset($match[1][0]) && $match[1][0] !== '' && str_contains($full, 'Tj')) {
                $chunks[] = $this->decodePdfHex($match[1][0]);
                continue;
            }

            if (isset($match[2][0]) && str_contains($full, 'TJ')) {
                $array = $match[2][0];
                $piece = '';
                if (preg_match_all('/\((\\\\.|[^\\\\)])*\)|<([0-9A-Fa-f\s]+)>/s', $array, $parts, PREG_SET_ORDER)) {
                    foreach ($parts as $part) {
                        if (str_starts_with($part[0], '(')) {
                            $piece .= $this->unescapePdf(substr($part[0], 1, -1));
                        } else {
                            $piece .= $this->decodePdfHex($part[2] ?? '');
                        }
                    }
                }
                $chunks[] = $piece;
            }
        }

        if ($chunks === []) {
            return '';
        }

        return implode(' ', $chunks);
    }

    private function unescapePdf(string $value): string
    {
        $value = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'], ["\n", "\r", "\t", '(', ')', '\\'], $value);
        $value = preg_replace_callback('/\\\\([0-7]{1,3})/', fn ($m) => chr(octdec($m[1])), $value) ?? $value;

        if (str_starts_with($value, "\xFE\xFF") || str_starts_with($value, "\xFF\xFE")) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-16') ?: $value;
        }

        return $value;
    }

    private function decodePdfHex(string $hex): string
    {
        $hex = preg_replace('/\s+/', '', $hex) ?? '';
        if ($hex === '') {
            return '';
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }
        $raw = @hex2bin($hex);
        if (! is_string($raw)) {
            return '';
        }
        if (str_starts_with($raw, "\xFE\xFF") || str_starts_with($raw, "\xFF\xFE")) {
            return mb_convert_encoding($raw, 'UTF-8', 'UTF-16') ?: $raw;
        }

        return $raw;
    }

    /**
     * @param  list<list<string>>  $grid
     */
    private function headerIndex(array $grid): ?int
    {
        foreach ($grid as $i => $line) {
            $blob = PersonName::key(implode(' ', $line));
            $hasName = str_contains($blob, 'colaborador')
                || str_contains($blob, 'funcionario')
                || (bool) preg_match('/\bnome\b/', $blob);
            $hasCode = str_contains($blob, 'codigo')
                || str_contains($blob, 'matricula')
                || str_contains($blob, 'registro');
            $hasAdmission = str_contains($blob, 'admiss');
            if ($hasName && ($hasCode || $hasAdmission)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $header
     * @return array{name:int,code:int,admission:int}
     */
    private function mapColumns(array $header): array
    {
        $map = ['name' => null, 'code' => null, 'admission' => null];

        foreach ($header as $i => $label) {
            $key = PersonName::key((string) $label);
            if (str_contains($key, 'colaborador') || str_contains($key, 'funcionario') || $key === 'nome' || str_starts_with($key, 'nome ')) {
                $map['name'] = $i;
            } elseif (str_contains($key, 'codigo') || str_contains($key, 'matricula') || str_contains($key, 'registro') || $key === 'code') {
                $map['code'] = $i;
            } elseif (str_contains($key, 'admiss')) {
                $map['admission'] = $i;
            }
        }

        if ($map['name'] === null || $map['code'] === null || $map['admission'] === null) {
            throw new InvalidArgumentException('A planilha precisa das colunas código, data de admissão e colaborador.');
        }

        return $map;
    }

    /**
     * @return list<list<string>>
     */
    private function csv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new InvalidArgumentException('Não foi possível ler o CSV.');
        }

        $rows = [];
        while (($line = fgetcsv($handle, 0, ',')) !== false) {
            if (count($line) === 1 && str_contains((string) $line[0], ';')) {
                $line = str_getcsv((string) $line[0], ';');
            }
            $rows[] = array_map(fn ($cell) => (string) $cell, $line);
        }
        fclose($handle);

        return $rows;
    }

    private function looksLikeSci(string $text): bool
    {
        return (bool) preg_match('/\d{2}\/\d{2}\/\d{4}.+\d{6}/', $text)
            || (bool) preg_match('/\d{1,6}.+\d{2}\/\d{2}\/\d{4}/', $text);
    }

    /**
     * @return list<array{code:string,name:string,admission_on:string}>
     */
    private function parseBlob(string $text): array
    {
        $blob = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $rows = [];
        $seen = [];

        $patterns = [
            '/(\d{2}\/\d{2}\/\d{2,4})\s*(.+?)\s*(\d{6})/u',
            '/(?<!\d)(\d{1,6})\s+([A-Za-zÀ-ÿ][^0-9]{2,}?)\s+(\d{2}\/\d{2}\/\d{2,4})/u',
        ];

        foreach ($patterns as $index => $pattern) {
            if (! preg_match_all($pattern, $blob, $matches, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($matches as $match) {
                $parsed = $index === 0
                    ? $this->normalize(trim($match[2]), $match[3], $match[1])
                    : $this->normalize(trim($match[2]), $match[1], $match[3]);
                $this->pushRow($rows, $seen, $parsed);
            }
            if ($rows !== []) {
                return $rows;
            }
        }

        return $rows;
    }

    /**
     * @param  list<array{code:string,name:string,admission_on:string}>  $rows
     * @param  array<string, true>  $seen
     * @param  array{code:string,name:string,admission_on:string}|null  $parsed
     */
    private function pushRow(array &$rows, array &$seen, ?array $parsed): void
    {
        if ($parsed === null) {
            return;
        }
        $key = $parsed['code'].'|'.$parsed['name'];
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $rows[] = $parsed;
    }

    private function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        $name = preg_replace('/^\d+\s*anos?\s*(e\s+\d+\s*meses?)?\s*/iu', '', $name) ?? $name;
        $name = preg_replace('/\b(codigo|matr[ií]cula|admiss[aã]o|colaborador|funcion[aá]rio|nome)\b/iu', ' ', $name) ?? $name;

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    private function normalizeExtractedText(string $text): string
    {
        $text = str_replace(["\x00", "\x0c"], "\n", $text);
        $text = preg_replace('/(\d{1,2})\s*[\/.\-]\s*(\d{1,2})\s*[\/.\-]\s*(\d{2,4})/', '$1/$2/$3', $text) ?? $text;
        $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text;

        return $text;
    }
}
