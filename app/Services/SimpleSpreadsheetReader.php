<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class SimpleSpreadsheetReader
{
    public function read(string $path): array
    {
        $header = file_get_contents($path, false, null, 0, 8);

        if ($header === "PK\x03\x04\x14\x00\x06\x00" || str_starts_with((string) $header, "PK\x03\x04")) {
            return $this->readXlsx($path);
        }

        if ($header === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            return $this->readXls($path);
        }

        throw new RuntimeException('不支持的 Excel 文件格式。');
    }

    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('无法打开 Excel 文件。');
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheets = $this->readSheetTargets($zip);
            $allRows = [];

            foreach ($sheets as $target) {
                $allRows = array_merge($allRows, $this->rowsFromXlsxSheet($zip, $target, $sharedStrings));
            }

            return $allRows;
        } finally {
            $zip->close();
        }
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $doc = simplexml_load_string($xml);
        $strings = [];
        foreach ($doc->si ?? [] as $si) {
            if (isset($si->t)) {
                $strings[] = (string) $si->t;
                continue;
            }

            $text = '';
            foreach ($si->r ?? [] as $run) {
                $text .= (string) $run->t;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function readSheetTargets(ZipArchive $zip): array
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relsXml === false) {
            return [];
        }

        $rels = [];
        $relsDoc = simplexml_load_string($relsXml);
        foreach ($relsDoc->Relationship ?? [] as $rel) {
            $attrs = $rel->attributes();
            $target = (string) $attrs['Target'];
            $rels[(string) $attrs['Id']] = str_starts_with($target, 'xl/')
                ? $target
                : 'xl/'.ltrim($target, '/');
        }

        $workbook = simplexml_load_string($workbookXml);
        $targets = [];
        foreach ($workbook->sheets->sheet ?? [] as $sheet) {
            $relationship = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $target = $rels[(string) $relationship['id']] ?? null;
            if ($target !== null) {
                $targets[] = $target;
            }
        }

        return $targets;
    }

    private function rowsFromXlsxSheet(ZipArchive $zip, string $target, array $sharedStrings): array
    {
        $xml = $zip->getFromName($target);
        if ($xml === false) {
            return [];
        }

        $doc = simplexml_load_string($xml);
        $rows = [];
        foreach ($doc->sheetData->row ?? [] as $row) {
            $values = [];
            foreach ($row->c ?? [] as $cell) {
                $attrs = $cell->attributes();
                $column = $this->columnIndex((string) $attrs['r']);
                $type = (string) ($attrs['t'] ?? '');
                $values[$column] = $this->xlsxCellValue($cell, $type, $sharedStrings);
            }

            $this->appendRow($rows, $values);
        }

        return $rows;
    }

    private function xlsxCellValue(\SimpleXMLElement $cell, string $type, array $sharedStrings): string
    {
        if ($type === 's') {
            return trim((string) ($sharedStrings[(int) $cell->v] ?? ''));
        }

        if ($type === 'inlineStr') {
            return trim((string) ($cell->is->t ?? ''));
        }

        return trim((string) ($cell->v ?? ''));
    }

    private function readXls(string $path): array
    {
        $workbook = $this->extractOleStream($path, ['Workbook', 'Book']);
        $sharedStrings = [];
        $rows = [];
        $offset = 0;
        $length = strlen($workbook);

        while ($offset + 4 <= $length) {
            $type = $this->u16($workbook, $offset);
            $size = $this->u16($workbook, $offset + 2);
            $data = substr($workbook, $offset + 4, $size);
            $offset += 4 + $size;

            if ($type === 0x00FC) {
                $sharedStrings = $this->parseSst($data);
                continue;
            }

            if ($type === 0x00FD && strlen($data) >= 10) {
                $row = $this->u16($data, 0);
                $col = $this->u16($data, 2);
                $sstIndex = $this->u32($data, 6);
                $rows[$row][$col] = $sharedStrings[$sstIndex] ?? '';
                continue;
            }

            if ($type === 0x0203 && strlen($data) >= 14) {
                $row = $this->u16($data, 0);
                $col = $this->u16($data, 2);
                $rows[$row][$col] = $this->formatNumeric(unpack('d', substr($data, 6, 8))[1]);
                continue;
            }

            if ($type === 0x027E && strlen($data) >= 10) {
                $row = $this->u16($data, 0);
                $col = $this->u16($data, 2);
                $rows[$row][$col] = $this->formatNumeric($this->decodeRk($this->u32($data, 6)));
                continue;
            }

            if ($type === 0x00BE && strlen($data) >= 6) {
                $row = $this->u16($data, 0);
                $firstCol = $this->u16($data, 2);
                $lastCol = $this->u16($data, 4);
                $cursor = 6;
                for ($col = $firstCol; $col <= $lastCol && $cursor + 6 <= strlen($data); $col++) {
                    $rk = $this->u32($data, $cursor + 2);
                    $rows[$row][$col] = $this->formatNumeric($this->decodeRk($rk));
                    $cursor += 6;
                }
            }
        }

        ksort($rows);
        $result = [];
        foreach ($rows as $cells) {
            $this->appendRow($result, $cells);
        }

        return $result;
    }

    private function extractOleStream(string $path, array $streamNames): string
    {
        $bytes = file_get_contents($path);
        $sectorSize = 1 << $this->u16($bytes, 30);
        $firstDirectorySector = $this->u32($bytes, 48);
        $firstFatSectors = [];

        for ($i = 0; $i < 109; $i++) {
            $sector = $this->u32($bytes, 76 + ($i * 4));
            if ($sector < 0xFFFFFFF0) {
                $firstFatSectors[] = $sector;
            }
        }

        $fat = [];
        foreach ($firstFatSectors as $sector) {
            $chunk = $this->sector($bytes, $sector, $sectorSize);
            for ($i = 0; $i < strlen($chunk); $i += 4) {
                $fat[] = $this->u32($chunk, $i);
            }
        }

        $directory = $this->readChain($bytes, $fat, $firstDirectorySector, $sectorSize);
        for ($offset = 0; $offset + 128 <= strlen($directory); $offset += 128) {
            $entry = substr($directory, $offset, 128);
            $nameLength = $this->u16($entry, 64);
            if ($nameLength < 2) {
                continue;
            }

            $name = mb_convert_encoding(substr($entry, 0, $nameLength - 2), 'UTF-8', 'UTF-16LE');
            if (! in_array($name, $streamNames, true)) {
                continue;
            }

            $startSector = $this->u32($entry, 116);
            $streamSize = $this->u32($entry, 120);

            return substr($this->readChain($bytes, $fat, $startSector, $sectorSize), 0, $streamSize);
        }

        throw new RuntimeException('未找到 Excel 工作簿数据。');
    }

    private function readChain(string $bytes, array $fat, int $startSector, int $sectorSize): string
    {
        $data = '';
        $sector = $startSector;
        $guard = 0;

        while ($sector < 0xFFFFFFF0 && isset($fat[$sector]) && $guard < 100000) {
            $data .= $this->sector($bytes, $sector, $sectorSize);
            $sector = $fat[$sector];
            $guard++;
        }

        return $data;
    }

    private function sector(string $bytes, int $sector, int $sectorSize): string
    {
        return substr($bytes, ($sector + 1) * $sectorSize, $sectorSize);
    }

    private function parseSst(string $data): array
    {
        if (strlen($data) < 8) {
            return [];
        }

        $count = $this->u32($data, 4);
        $offset = 8;
        $strings = [];

        for ($i = 0; $i < $count && $offset < strlen($data); $i++) {
            $strings[] = $this->readBiffString($data, $offset);
        }

        return $strings;
    }

    private function readBiffString(string $data, int &$offset): string
    {
        if ($offset + 3 > strlen($data)) {
            return '';
        }

        $charCount = $this->u16($data, $offset);
        $offset += 2;
        $flags = ord($data[$offset]);
        $offset++;

        $hasRichText = ($flags & 0x08) !== 0;
        $hasExt = ($flags & 0x04) !== 0;
        $isUtf16 = ($flags & 0x01) !== 0;
        $richRuns = 0;
        $extSize = 0;

        if ($hasRichText) {
            $richRuns = $this->u16($data, $offset);
            $offset += 2;
        }

        if ($hasExt) {
            $extSize = $this->u32($data, $offset);
            $offset += 4;
        }

        $byteLength = $charCount * ($isUtf16 ? 2 : 1);
        $raw = substr($data, $offset, $byteLength);
        $offset += $byteLength + ($richRuns * 4) + $extSize;

        return trim($isUtf16 ? mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE') : $raw);
    }

    private function decodeRk(int $rk): float
    {
        $value = ($rk & 0x02) ? ($rk >> 2) : unpack('d', pack('V2', 0, $rk & 0xFFFFFFFC))[1];

        return ($rk & 0x01) ? $value / 100 : $value;
    }

    private function formatNumeric(float $value): string
    {
        if (floor($value) === $value) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.');
    }

    private function appendRow(array &$rows, array $values): void
    {
        if ($values === []) {
            return;
        }

        ksort($values);
        $rows[] = array_map(
            fn (int $index) => $values[$index] ?? '',
            range(0, max(array_keys($values)))
        );
    }

    private function columnIndex(string $cellReference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($cellReference));
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private function u16(string $bytes, int $offset): int
    {
        return unpack('v', substr($bytes, $offset, 2))[1];
    }

    private function u32(string $bytes, int $offset): int
    {
        return unpack('V', substr($bytes, $offset, 4))[1];
    }
}
