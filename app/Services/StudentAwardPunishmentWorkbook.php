<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class StudentAwardPunishmentWorkbook
{
    public const REWARD_SHEET = '奖励';

    public const PUNISHMENT_SHEET = '惩罚';

    public function import(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('无法打开 Excel 文件。');
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheets = $this->readSheetTargets($zip);

            return [
                'rewards' => $this->rowsFromSheet($zip, $sheets[self::REWARD_SHEET] ?? null, $sharedStrings),
                'punishments' => $this->rowsFromSheet($zip, $sheets[self::PUNISHMENT_SHEET] ?? null, $sharedStrings),
            ];
        } finally {
            $zip->close();
        }
    }

    public function createTemplate(string $path, ?array $rewardRows = null, ?array $punishmentRows = null): void
    {
        $rewardRows ??= [
            ['学号', '姓名', '奖励名称', '年度', '等级'],
            ['20260001', '张三', '全国大学生数学竞赛一等奖', '2026', '国家级'],
            ['20260002', '李四', '优秀学生干部', '2026', '校级'],
        ];

        $punishmentRows ??= [
            ['学号', '姓名', '惩罚原因', '惩罚时间', '发生年度'],
            ['20260003', '王五', '考试违纪', '2026-04-12', '2026'],
            ['20260004', '赵六', '宿舍违规用电', '2026-05-01', '2026'],
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
            $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($rewardRows));
            $zip->addFromString('xl/worksheets/sheet2.xml', $this->sheetXml($punishmentRows));
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
            $rels[(string) $attrs['Id']] = 'xl/'.ltrim((string) $attrs['Target'], '/');
        }

        $workbook = simplexml_load_string($workbookXml);
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $sheets = [];
        foreach ($workbook->sheets->sheet ?? [] as $sheet) {
            $attrs = $sheet->attributes();
            $relationship = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $target = $rels[(string) $relationship['id']] ?? null;
            if ($target !== null) {
                $sheets[(string) $attrs['name']] = $target;
            }
        }

        return $sheets;
    }

    private function rowsFromSheet(ZipArchive $zip, ?string $target, array $sharedStrings): array
    {
        if ($target === null) {
            return [];
        }

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
            .'<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
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
            .'<sheets>'
            .'<sheet name="奖励" sheetId="1" r:id="rId1"/>'
            .'<sheet name="惩罚" sheetId="2" r:id="rId2"/>'
            .'</sheets>'
            .'</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
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
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>';

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
