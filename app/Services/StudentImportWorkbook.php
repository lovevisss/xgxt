<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class StudentImportWorkbook
{
    public function read(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheets = [];

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $rows = [];
            $highestRow = $worksheet->getHighestDataRow();
            $highestColumn = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());

            for ($row = 1; $row <= $highestRow; $row++) {
                $values = [];
                for ($column = 1; $column <= $highestColumn; $column++) {
                    $values[] = trim((string) $worksheet->getCell([$column, $row])->getFormattedValue());
                }
                if (! $this->isBlankRow($values)) {
                    $rows[] = $values;
                }
            }

            $sheets[$worksheet->getTitle()] = $rows;
        }

        $spreadsheet->disconnectWorksheets();

        return $sheets;
    }

    public function write(string $path, array $sheets): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $name => $rows) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($name);

            foreach ($rows as $rowIndex => $row) {
                foreach ($row as $columnIndex => $value) {
                    $sheet->setCellValue([$columnIndex + 1, $rowIndex + 1], $value);
                }
            }

            $highestColumn = count($rows[0] ?? []);
            for ($column = 1; $column <= $highestColumn; $column++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
            }
            $sheet->freezePane('A2');
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
