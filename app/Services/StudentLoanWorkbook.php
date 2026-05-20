<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class StudentLoanWorkbook
{
    public function import(string $path): array
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
                $allRows = array_merge($allRows, $this->rowsFromSheet($zip, $target, $sharedStrings));
            }

            return $allRows;
        } finally {
            $zip->close();
        }
    }

    public function createTemplate(string $path, ?array $rows = null): void
    {
        $rows ??= [
            ['2025年生源地贷款到款名单汇总（示例）', '', '', '', '', '', '', ''],
            ['序号', '身份证号码', '学号', '姓名', '二级学院', '班级', '金额', '备注'],
            ['1', '320100200501010001', '20250001', '张三', '会计学院', '25会计1', '12000', '国开行'],
            ['2', '320100200502020002', '20250002', '李四', '信息与人工智能学院', '25计算机1', '16000', '招商银行'],
        ];

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('无法创建 Excel 模板。');
        }

        try {
            $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
            $zip->addFromString('_rels/.rels', $this->rootRelsXml());
            $zip->addFromString('xl/workbook.xml', $this->workbookXml());
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
            $zip->addFromString('xl/styles.xml', $this->stylesXml());
            $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($rows));
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

    private function rowsFromSheet(ZipArchive $zip, string $target, array $sharedStrings): array
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
                $values[$column] = $this->cellValue($cell, $type, $sharedStrings);
            }

            if ($values !== []) {
                ksort($values);
                $rows[] = array_map(
                    fn (int $index) => $values[$index] ?? '',
                    range(0, max(array_keys($values)))
                );
            }
        }

        return $rows;
    }

    private function cellValue(\SimpleXMLElement $cell, string $type, array $sharedStrings): string
    {
        if ($type === 's') {
            return trim((string) ($sharedStrings[(int) $cell->v] ?? ''));
        }

        if ($type === 'inlineStr') {
            return trim((string) ($cell->is->t ?? ''));
        }

        return trim((string) ($cell->v ?? ''));
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

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="助学贷款" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            .'</styleSheet>';
    }

    private function sheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $xml .= '<row r="'.$excelRow.'">';
            foreach ($row as $colIndex => $value) {
                $cell = $this->columnName($colIndex + 1).$excelRow;
                $xml .= '<c r="'.$cell.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }
}
