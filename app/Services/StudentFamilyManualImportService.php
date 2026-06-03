<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class StudentFamilyManualImportService
{
    public function __construct(private readonly StudentImportWorkbook $workbook)
    {
    }

    public function import(string $path, ?string $extension = null, ?callable $progress = null): array
    {
        @set_time_limit(180);

        $extension = strtolower((string) ($extension ?: pathinfo($path, PATHINFO_EXTENSION)));
        $result = ['imported' => 0, 'students' => 0, 'skipped' => 0, 'errors' => []];
        $studentNumbers = [];
        $batch = [];
        $seenKeys = [];

        $flush = function () use (&$batch, &$result, &$studentNumbers, $progress): void {
            if ($batch === []) {
                return;
            }

            DB::table('student_families')->upsert(
                $batch,
                ['sync_key'],
                [
                    'stu_no',
                    'name',
                    'relationship',
                    'specific_relationship',
                    'work_unit',
                    'position',
                    'phone',
                    'is_emergency_contact',
                    'synced_at',
                    'is_local_modified',
                    'local_modified_at',
                    'updated_at',
                ]
            );

            $result['imported'] += count($batch);
            $result['students'] = count($studentNumbers);
            $batch = [];

            if ($progress !== null) {
                $progress($result);
            }
        };

        foreach ($this->rows($path, $extension) as $sheetName => $rows) {
            $headers = null;
            $groups = [];
            $studentNumberIndex = null;
            $sheetHadData = false;

            foreach ($rows as $rowNumber => $row) {
                if (! $this->isBlankRow($row)) {
                    $sheetHadData = true;
                }

                if ($headers === null) {
                    $detected = $this->detectHeaders($row);
                    if ($detected === null) {
                        continue;
                    }

                    [$headers, $studentNumberIndex, $groups] = $detected;
                    continue;
                }

                if ($this->isBlankRow($row)) {
                    continue;
                }

                $studentNumber = $this->value($row, $studentNumberIndex);
                if ($studentNumber === '') {
                    $result['skipped']++;
                    continue;
                }

                $rowSeen = [];
                foreach ($groups as $group) {
                    $record = $this->recordFromGroup($row, $studentNumber, $group);
                    if ($record === null) {
                        continue;
                    }

                    $dedupeKey = implode('|', [
                        $record['stu_no'],
                        $record['name'] ?? '',
                        $record['relationship'] ?? '',
                        $record['phone'] ?? '',
                        $record['work_unit'] ?? '',
                    ]);

                    if (isset($rowSeen[$dedupeKey]) || isset($seenKeys[$record['sync_key']])) {
                        continue;
                    }

                    $rowSeen[$dedupeKey] = true;
                    $seenKeys[$record['sync_key']] = true;
                    $batch[] = $record;
                    $studentNumbers[$studentNumber] = true;

                    if (count($batch) >= 1000) {
                        $flush();
                    }
                }
            }

            if ($headers === null && $sheetHadData) {
                $result['errors'][] = "{$sheetName} 未找到表头：需要包含“学号”和“家庭成员称谓1/姓名1/联系电话1”等列。";
            }
        }

        $flush();

        $result['students'] = count($studentNumbers);

        return $result;
    }

    private function rows(string $path, string $extension): iterable
    {
        if ($extension === 'xlsx') {
            yield from $this->xlsxRows($path);

            return;
        }

        foreach ($this->workbook->read($path) as $sheetName => $rows) {
            yield $sheetName => $rows;
        }
    }

    private function xlsxRows(string $path): iterable
    {
        $reader = new XlsxReader();
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                yield $sheet->getName() => (function () use ($sheet): iterable {
                    foreach ($sheet->getRowIterator() as $row) {
                        yield array_map(fn ($value) => $this->normalizeCell($value), $row->toArray());
                    }
                })();
            }
        } finally {
            $reader->close();
        }
    }

    private function detectHeaders(array $row): ?array
    {
        $studentNumberIndex = null;
        $groups = [];

        foreach ($row as $index => $header) {
            $normalized = $this->normalizeHeader($header);

            if ($normalized === '学号') {
                $studentNumberIndex = $index;
                continue;
            }

            if (preg_match('/家庭成员称谓(\d+)/u', $normalized, $matches)) {
                $groups[(int) $matches[1]]['relationship'] = $index;
                continue;
            }

            if (preg_match('/^姓名(\d+)$/u', $normalized, $matches)) {
                $groups[(int) $matches[1]]['name'] = $index;
                continue;
            }

            if (preg_match('/工作单位(\d+)/u', $normalized, $matches)) {
                $groups[(int) $matches[1]]['work_unit'] = $index;
                continue;
            }

            if (preg_match('/职务(\d+)/u', $normalized, $matches)) {
                $groups[(int) $matches[1]]['position'] = $index;
                continue;
            }

            if (preg_match('/联系电话(\d+)/u', $normalized, $matches)) {
                $groups[(int) $matches[1]]['phone'] = $index;
            }
        }

        $groups = array_values(array_filter($groups, fn (array $group) => isset($group['name']) || isset($group['phone'])));

        if ($studentNumberIndex === null || $groups === []) {
            return null;
        }

        return [$row, $studentNumberIndex, $groups];
    }

    private function recordFromGroup(array $row, string $studentNumber, array $group): ?array
    {
        $name = $this->nullableValue($this->value($row, $group['name'] ?? null));
        $phone = $this->nullableValue($this->value($row, $group['phone'] ?? null));

        if ($name === null && $phone === null) {
            return null;
        }

        $relationship = $this->nullableValue($this->value($row, $group['relationship'] ?? null));
        $workUnit = $this->nullableValue($this->value($row, $group['work_unit'] ?? null));
        $position = $this->nullableValue($this->value($row, $group['position'] ?? null));
        $now = now();

        return [
            'sync_key' => md5(implode('|', ['manual-family', $studentNumber, $name ?? '', $relationship ?? '', $phone ?? ''])),
            'stu_no' => $studentNumber,
            'name' => $name ?? '',
            'relationship' => $relationship,
            'specific_relationship' => $relationship,
            'work_unit' => $workUnit,
            'position' => $position,
            'phone' => $phone,
            'is_emergency_contact' => false,
            'synced_at' => null,
            'is_local_modified' => true,
            'local_modified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function normalizeCell(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return floor($value) === $value ? number_format($value, 0, '.', '') : rtrim(rtrim((string) $value, '0'), '.');
        }

        return trim(str_replace("\xc2\xa0", ' ', (string) $value));
    }

    private function normalizeHeader(string $value): string
    {
        return preg_replace('/\s+/u', '', $this->normalizeCell($value)) ?? '';
    }

    private function value(array $row, ?int $index): string
    {
        if ($index === null) {
            return '';
        }

        return $this->normalizeCell($row[$index] ?? '');
    }

    private function nullableValue(string $value): ?string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        if (in_array(mb_strtolower($normalized), ['无', '暂无', '不详', '未知', '不知道', '/', '\\', '-', '--', 'null'], true)) {
            return null;
        }

        return $normalized;
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->normalizeCell($value) !== '') {
                return false;
            }
        }

        return true;
    }

}
