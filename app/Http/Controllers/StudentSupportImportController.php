<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentSupportRecipient;
use App\Services\StudentSupportWorkbook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class StudentSupportImportController extends Controller
{
    public function page()
    {
        return view('student-support-import');
    }

    public function template(StudentSupportWorkbook $workbook)
    {
        $path = storage_path('app/student-support-template.xlsx');
        $workbook->createTemplate($path);

        return Response::download($path, '学生资助对象导入示例.xlsx')->deleteFileAfterSend();
    }

    public function import(Request $request, StudentSupportWorkbook $workbook)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xls,xlsx', 'max:20480'],
            'academic_year' => ['nullable', 'string', 'max:16'],
        ]);

        $rows = $workbook->import($request->file('file')->getRealPath());
        $academicYear = trim((string) $request->input('academic_year', '')) ?: $this->inferAcademicYear($rows);
        $records = $this->recordsFromRows($rows, $academicYear);
        $result = [
            'imported' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($records, &$result): void {
            foreach ($records as $index => $record) {
                $validator = Validator::make($record, [
                    'student_xgh' => ['required', 'string'],
                    'student_name' => ['nullable', 'string'],
                    'gender' => ['nullable', 'string'],
                    'college' => ['nullable', 'string'],
                    'major' => ['nullable', 'string'],
                    'support_level' => ['required', 'string'],
                    'academic_year' => ['required', 'string'],
                ]);

                if ($validator->fails()) {
                    $result['errors'][] = '第 '.($index + 1).' 条：'.$validator->errors()->first();
                    continue;
                }

                StudentSupportRecipient::query()->updateOrCreate(
                    [
                        'student_xgh' => $record['student_xgh'],
                        'academic_year' => $record['academic_year'],
                    ],
                    [
                        'student_name' => $this->studentName($record['student_xgh'], $record['student_name']),
                        'gender' => $record['gender'],
                        'college' => $record['college'],
                        'major' => $record['major'],
                        'support_level' => $record['support_level'],
                        'imported_at' => now(),
                    ]
                );
                $result['imported']++;
            }
        });

        return response()->json($result);
    }

    private function recordsFromRows(array $rows, string $academicYear): array
    {
        $headerIndex = $this->headerIndex($rows);
        if ($headerIndex === null) {
            return [[
                'student_xgh' => '',
                'support_level' => '',
                'academic_year' => $academicYear,
            ]];
        }

        $records = [];
        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $records[] = [
                'student_xgh' => $this->cell($row, 1),
                'student_name' => $this->cell($row, 2),
                'gender' => $this->cell($row, 3),
                'college' => $this->cell($row, 4),
                'major' => $this->cell($row, 5),
                'support_level' => $this->cell($row, 6),
                'academic_year' => $academicYear,
            ];
        }

        return $records;
    }

    private function headerIndex(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $joined = implode('|', array_map(fn ($value) => trim((string) $value), $row));
            if (str_contains($joined, '学号') && str_contains($joined, '资助等级')) {
                return $index;
            }
        }

        return null;
    }

    private function inferAcademicYear(array $rows): string
    {
        foreach (array_slice($rows, 0, 5) as $row) {
            $text = implode(' ', $row);
            if (preg_match('/(20\d{2})\s*[-—~至]\s*(20\d{2})/', $text, $matches)) {
                return $matches[1].'-'.$matches[2];
            }
        }

        return date('Y').'-'.((int) date('Y') + 1);
    }

    private function cell(array $row, int $index): string
    {
        return trim((string) ($row[$index] ?? ''));
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
