<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentLoan;
use App\Services\StudentLoanWorkbook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class StudentLoanImportController extends Controller
{
    public function page()
    {
        return view('student-loan-import');
    }

    public function template(StudentLoanWorkbook $workbook)
    {
        $path = storage_path('app/student-loan-template.xlsx');
        $workbook->createTemplate($path);

        return Response::download($path, '学生助学贷款导入示例.xlsx')->deleteFileAfterSend();
    }

    public function import(Request $request, StudentLoanWorkbook $workbook)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'annual_year' => ['nullable', 'integer', 'between:1900,2100'],
            'source' => ['nullable', 'string', 'max:64'],
        ]);

        $rows = $workbook->import($request->file('file')->getRealPath());
        $annualYear = $request->integer('annual_year') ?: $this->inferYear($rows);
        $source = trim((string) $request->input('source', '国开行')) ?: '国开行';
        $records = $this->recordsFromRows($rows, $annualYear, $source);
        $result = [
            'imported' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($records, &$result): void {
            foreach ($records as $index => $record) {
                $validator = Validator::make($record, [
                    'student_xgh' => ['required', 'string'],
                    'student_name' => ['nullable', 'string'],
                    'id_card' => ['nullable', 'string'],
                    'college' => ['nullable', 'string'],
                    'class_name' => ['nullable', 'string'],
                    'amount' => ['required', 'numeric', 'min:0'],
                    'annual_year' => ['required', 'integer', 'between:1900,2100'],
                    'source' => ['nullable', 'string'],
                    'remark' => ['nullable', 'string'],
                ]);

                if ($validator->fails()) {
                    $result['errors'][] = '第 '.($index + 1).' 条：'.$validator->errors()->first();
                    continue;
                }

                $studentName = $this->studentName($record['student_xgh'], $record['student_name']);
                StudentLoan::query()->updateOrCreate(
                    [
                        'student_xgh' => $record['student_xgh'],
                        'annual_year' => $record['annual_year'],
                        'source' => $record['source'],
                    ],
                    [
                        'student_name' => $studentName,
                        'id_card' => $record['id_card'],
                        'college' => $record['college'],
                        'class_name' => $record['class_name'],
                        'amount' => $record['amount'],
                        'remark' => $record['remark'],
                        'imported_at' => now(),
                    ]
                );
                $result['imported']++;
            }
        });

        return response()->json($result);
    }

    private function recordsFromRows(array $rows, ?int $annualYear, string $source): array
    {
        $headerIndex = $this->headerIndex($rows);
        if ($headerIndex === null) {
            return [[
                'student_xgh' => '',
                'amount' => null,
                'annual_year' => $annualYear,
                'source' => $source,
            ]];
        }

        $records = [];
        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $records[] = [
                'student_xgh' => $this->cell($row, 2),
                'student_name' => $this->cell($row, 3),
                'id_card' => $this->cell($row, 1),
                'college' => $this->cell($row, 4),
                'class_name' => $this->cell($row, 5),
                'amount' => $this->amount($this->cell($row, 6)),
                'annual_year' => $annualYear,
                'source' => $source,
                'remark' => $this->cell($row, 7),
            ];
        }

        return $records;
    }

    private function headerIndex(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $joined = implode('|', array_map(fn ($value) => trim((string) $value), $row));
            if (str_contains($joined, '学号') && str_contains($joined, '金额')) {
                return $index;
            }
        }

        return null;
    }

    private function inferYear(array $rows): ?int
    {
        foreach (array_slice($rows, 0, 5) as $row) {
            $text = implode(' ', $row);
            if (preg_match('/(19|20)\d{2}/', $text, $matches)) {
                return (int) $matches[0];
            }
        }

        return null;
    }

    private function cell(array $row, int $index): string
    {
        return trim((string) ($row[$index] ?? ''));
    }

    private function amount(string $value): ?float
    {
        $normalized = str_replace([',', '，', '￥', '元', ' '], '', $value);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function isBlankRow(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }

    private function studentName(string $studentNumber, ?string $fallback): ?string
    {
        return Student::query()->where('xgh', $studentNumber)->value('xm') ?: $fallback;
    }
}
