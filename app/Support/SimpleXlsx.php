<?php

namespace App\Support;

use InvalidArgumentException;
use SimpleXMLElement;
use ZipArchive;

class SimpleXlsx
{
    /**
     * @return list<list<string>>
     */
    public static function rows(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('Não foi possível abrir a planilha.');
        }

        $strings = self::sharedStrings($zip);
        $sheetXml = $zip->getFromName(self::firstSheetPath($zip));
        $zip->close();

        if ($sheetXml === false) {
            throw new InvalidArgumentException('A planilha não tem aba de dados.');
        }

        $sheet = self::xml($sheetXml);
        $grid = [];
        $maxRow = 0;
        $maxCol = 0;

        foreach (self::named($sheet, 'c') as $cell) {
            $ref = (string) $cell['r'];
            if ($ref === '') {
                continue;
            }
            [$col, $row] = self::cellIndex($ref);
            $value = self::cellValue($cell, $strings);
            $grid[$row][$col] = $value;
            $maxRow = max($maxRow, $row);
            $maxCol = max($maxCol, $col);
        }

        $rows = [];
        for ($r = 1; $r <= $maxRow; $r++) {
            $line = [];
            for ($c = 0; $c <= $maxCol; $c++) {
                $line[] = (string) ($grid[$r][$c] ?? '');
            }
            $rows[] = $line;
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $root = self::xml($xml);
        $out = [];
        foreach (self::named($root, 'si') as $si) {
            $texts = [];
            foreach (self::named($si, 't') as $t) {
                $texts[] = (string) $t;
            }
            $out[] = implode('', $texts);
        }

        return $out;
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($rels === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $xml = simplexml_load_string($rels);
        if (! $xml instanceof SimpleXMLElement) {
            return 'xl/worksheets/sheet1.xml';
        }

        foreach ($xml->Relationship as $rel) {
            $target = (string) $rel['Target'];
            if (str_contains($target, 'worksheets/')) {
                return 'xl/'.ltrim($target, '/');
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private static function xml(string $payload): SimpleXMLElement
    {
        $root = simplexml_load_string($payload);
        if (! $root instanceof SimpleXMLElement) {
            throw new InvalidArgumentException('Planilha inválida.');
        }

        return $root;
    }

    /**
     * @return list<SimpleXMLElement>
     */
    private static function named(SimpleXMLElement $node, string $local): array
    {
        $found = $node->xpath('.//*[local-name()="'.$local.'"]');

        return $found ?: [];
    }

    /**
     * @return array{0:int,1:int}
     */
    private static function cellIndex(string $ref): array
    {
        preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
        $col = 0;
        foreach (str_split($m[1] ?? 'A') as $ch) {
            $col = ($col * 26) + (ord($ch) - 64);
        }

        return [$col - 1, (int) ($m[2] ?? 1)];
    }

    /**
     * @param  list<string>  $strings
     */
    private static function cellValue(SimpleXMLElement $cell, array $strings): string
    {
        $type = (string) $cell['t'];
        $raw = '';
        foreach (self::named($cell, 'v') as $v) {
            $raw = (string) $v;
            break;
        }

        if ($type === 's') {
            return $strings[(int) $raw] ?? '';
        }

        if ($type === 'inlineStr') {
            $parts = [];
            foreach (self::named($cell, 't') as $t) {
                $parts[] = (string) $t;
            }

            return implode('', $parts);
        }

        return $raw;
    }
}
