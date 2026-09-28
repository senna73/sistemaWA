<?php

namespace App\Services\Rh;

use App\Support\PersonName;
use App\Support\SimpleXlsx;
use InvalidArgumentException;

class CliomedReportParser
{
    /**
     * @return list<array{name:string,unit:?string,sector:?string,role:?string}>
     */
    public function parse(string $path, ?string $originalName = null): array
    {
        $extension = strtolower(pathinfo($originalName ?: $path, PATHINFO_EXTENSION));
        $grid = $extension === 'csv' ? $this->csv($path) : SimpleXlsx::rows($path);

        $headerAt = $this->headerIndex($grid);
        if ($headerAt === null) {
            throw new InvalidArgumentException('Não encontrei as colunas Nome Unidade / Nome Funcionário na planilha.');
        }

        $map = $this->mapColumns($grid[$headerAt]);
        $rows = [];

        for ($i = $headerAt + 1; $i < count($grid); $i++) {
            $line = $grid[$i];
            $name = trim((string) ($line[$map['name']] ?? ''));
            if ($name === '' || PersonName::key($name) === '') {
                continue;
            }

            $rows[] = [
                'name' => $name,
                'unit' => $this->cell($line, $map['unit'] ?? null),
                'sector' => $this->cell($line, $map['sector'] ?? null),
                'role' => $this->cell($line, $map['role'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<list<string>>  $grid
     */
    private function headerIndex(array $grid): ?int
    {
        foreach ($grid as $i => $line) {
            foreach ($line as $cell) {
                $key = PersonName::key((string) $cell);
                if (str_contains($key, 'funcionario') || $key === 'nome') {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $header
     * @return array{name:int,unit:?int,sector:?int,role:?int}
     */
    private function mapColumns(array $header): array
    {
        $map = ['name' => null, 'unit' => null, 'sector' => null, 'role' => null];

        foreach ($header as $i => $label) {
            $key = PersonName::key((string) $label);
            if (str_contains($key, 'funcionario') || $key === 'nome') {
                $map['name'] = $i;
            } elseif (str_contains($key, 'unidade') || str_contains($key, 'empresa')) {
                $map['unit'] = $i;
            } elseif (str_contains($key, 'setor')) {
                $map['sector'] = $i;
            } elseif (str_contains($key, 'cargo')) {
                $map['role'] = $i;
            }
        }

        if ($map['name'] === null) {
            $map['name'] = count($header) - 1;
        }

        return $map;
    }

    /**
     * @param  list<string>  $line
     */
    private function cell(array $line, ?int $index): ?string
    {
        if ($index === null) {
            return null;
        }

        $value = trim((string) ($line[$index] ?? ''));

        return $value === '' ? null : $value;
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
        while (($line = fgetcsv($handle, 0, ';')) !== false) {
            if (count($line) === 1 && str_contains((string) $line[0], ',')) {
                $line = str_getcsv((string) $line[0], ',');
            }
            $rows[] = array_map(fn ($v) => (string) $v, $line);
        }
        fclose($handle);

        return $rows;
    }
}
