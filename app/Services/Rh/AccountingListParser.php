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

        if ($extension === 'pdf') {
            return $this->fromText($this->pdfText($path));
        }

        if (in_array($extension, ['xlsx', 'xls'], true)) {
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
        $rows = [];
        $seen = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $parsed = $this->parseSciLine(trim($line));
            if ($parsed === null) {
                continue;
            }
            $key = $parsed['code'].'|'.$parsed['name'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $rows[] = $parsed;
        }

        if ($rows === []) {
            $blob = preg_replace('/\s+/u', ' ', $text) ?? $text;
            if (preg_match_all('/(\d{2}\/\d{2}\/\d{4})\s*(.+?)\s*(\d{6})/u', $blob, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $parsed = $this->normalize(trim($match[2]), $match[3], $match[1]);
                    if ($parsed === null) {
                        continue;
                    }
                    $key = $parsed['code'].'|'.$parsed['name'];
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $rows[] = $parsed;
                }
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
        if ($headerAt === null) {
            $joined = [];
            foreach ($grid as $line) {
                $joined[] = implode(' ', $line);
            }

            return $this->fromText(implode("\n", $joined));
        }

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

        if ($rows === []) {
            throw new InvalidArgumentException('A planilha não tem linhas com nome, código e data de admissão.');
        }

        return $rows;
    }

    /**
     * @return array{code:string,name:string,admission_on:string}|null
     */
    public function parseSciLine(string $line): ?array
    {
        if ($line === '' || str_contains(mb_strtolower($line), 'total de colaboradores')) {
            return null;
        }

        if (! preg_match('/(\d{2}\/\d{2}\/\d{4})\s*(.+?)(\d{6})\s*$/u', $line, $match)) {
            return null;
        }

        return $this->normalize(trim($match[2]), $match[3], $match[1]);
    }

    /**
     * @return array{code:string,name:string,admission_on:string}|null
     */
    private function normalize(string $name, string $code, string $admission): ?array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        $digits = preg_replace('/\D/', '', $code) ?: '';
        if ($name === '' || PersonName::key($name) === '' || strlen($digits) < 1) {
            return null;
        }

        try {
            $date = str_contains($admission, '/')
                ? Carbon::createFromFormat('d/m/Y', $admission)
                : Carbon::parse($admission);
        } catch (\Throwable) {
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
                $raw = ltrim($stream, "\r\n");
                $decoded = @gzuncompress($raw);
                if ($decoded === false) {
                    $decoded = @gzinflate($raw);
                }
                if (! is_string($decoded) || $decoded === '') {
                    continue;
                }
                $out .= $this->pdfOperators($decoded)."\n";
            }
        }

        if (trim($out) === '') {
            $out = $this->pdfOperators($data);
        }

        $out = preg_replace('/[^\P{C}\n]+/u', ' ', $out) ?? $out;

        return $out;
    }

    private function pdfOperators(string $decoded): string
    {
        $text = '';
        if (preg_match_all('/\((\\\\.|[^\\\\)])*\)\s*Tj/s', $decoded, $matches)) {
            foreach ($matches[0] as $token) {
                if (preg_match('/^\((.*)\)\s*Tj/s', $token, $inner)) {
                    $text .= $this->unescapePdf($inner[1]);
                }
            }
        }
        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $tj)) {
            foreach ($tj[1] as $array) {
                if (preg_match_all('/\((\\\\.|[^\\\\)])*\)/s', $array, $parts)) {
                    foreach ($parts[0] as $part) {
                        $text .= $this->unescapePdf(substr($part, 1, -1));
                    }
                }
            }
        }

        return $text;
    }

    private function unescapePdf(string $value): string
    {
        $value = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)'], ["\n", "\r", "\t", '(', ')'], $value);

        return stripcslashes($value);
    }

    /**
     * @param  list<list<string>>  $grid
     */
    private function headerIndex(array $grid): ?int
    {
        foreach ($grid as $i => $line) {
            $blob = PersonName::key(implode(' ', $line));
            if (str_contains($blob, 'colaborador') && (str_contains($blob, 'admiss') || str_contains($blob, 'codigo'))) {
                return $i;
            }
            if (str_contains($blob, 'nome') && str_contains($blob, 'codigo')) {
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
            if (str_contains($key, 'colaborador') || $key === 'nome') {
                $map['name'] = $i;
            } elseif (str_contains($key, 'codigo') || $key === 'code') {
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
            $rows[] = array_map(fn ($cell) => (string) $cell, $line);
        }
        fclose($handle);

        return $rows;
    }

    private function looksLikeSci(string $text): bool
    {
        return (bool) preg_match('/\d{2}\/\d{2}\/\d{4}.+\d{6}/', $text);
    }
}
