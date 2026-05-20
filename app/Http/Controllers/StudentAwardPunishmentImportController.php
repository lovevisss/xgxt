<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentAward;
use App\Models\StudentPunishment;
use App\Services\StudentAwardPunishmentWorkbook;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class StudentAwardPunishmentImportController extends Controller
{
    public function page()
    {
        return view('student-award-punishment-import');
    }

    public function template(StudentAwardPunishmentWorkbook $workbook)
    {
        $path = storage_path('app/student-award-punishment-template.xlsx');
        $workbook->createTemplate($path);

        return Response::download($path, '学生奖惩导入示例.xlsx')->deleteFileAfterSend();
    }

    public function import(Request $request, StudentAwardPunishmentWorkbook $workbook)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);

        $data = $workbook->import($request->file('file')->getRealPath());
        $result = [
            'reward_imported' => 0,
            'punishment_imported' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($data, &$result): void {
            foreach ($this->recordsFromRows($data['rewards'], 'reward') as $index => $record) {
                $validator = Validator::make($record, [
                    'student_xgh' => ['required', 'string'],
                    'student_name' => ['nullable', 'string'],
                    'award_name' => ['required', 'string'],
                    'annual_year' => ['required', 'integer', 'between:1900,2100'],
                    'level' => ['nullable', 'string'],
                ]);

                if ($validator->fails()) {
                    $result['errors'][] = '奖励第 '.($index + 2).' 行：'.$validator->errors()->first();
                    continue;
                }

                $studentName = $this->studentName($record['student_xgh'], $record['student_name']);
                StudentAward::query()->updateOrCreate(
                    [
                        'student_xgh' => $record['student_xgh'],
                        'award_name' => $record['award_name'],
                        'annual_year' => $record['annual_year'],
                        'level' => $record['level'],
                    ],
                    [
                        'student_name' => $studentName,
                        'imported_at' => now(),
                    ]
                );
                $result['reward_imported']++;
            }

            foreach ($this->recordsFromRows($data['punishments'], 'punishment') as $index => $record) {
                $validator = Validator::make($record, [
                    'student_xgh' => ['required', 'string'],
                    'student_name' => ['nullable', 'string'],
                    'reason' => ['required', 'string'],
                    'punished_at' => ['nullable', 'date'],
                    'annual_year' => ['required', 'integer', 'between:1900,2100'],
                ]);

                if ($validator->fails()) {
                    $result['errors'][] = '惩罚第 '.($index + 2).' 行：'.$validator->errors()->first();
                    continue;
                }

                $studentName = $this->studentName($record['student_xgh'], $record['student_name']);
                StudentPunishment::query()->updateOrCreate(
                    [
                        'student_xgh' => $record['student_xgh'],
                        'reason' => $record['reason'],
                        'punished_at' => $record['punished_at'],
                        'annual_year' => $record['annual_year'],
                    ],
                    [
                        'student_name' => $studentName,
                        'imported_at' => now(),
                    ]
                );
                $result['punishment_imported']++;
            }
        });

        return response()->json($result);
    }

    private function recordsFromRows(array $rows, string $type): array
    {
        if (count($rows) < 2) {
            return [];
        }

        $records = [];
        foreach (array_slice($rows, 1) as $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            if ($type === 'reward') {
                $records[] = [
                    'student_xgh' => $this->cell($row, 0),
                    'student_name' => $this->cell($row, 1),
                    'award_name' => $this->cell($row, 2),
                    'annual_year' => $this->year($this->cell($row, 3)),
                    'level' => $this->cell($row, 4),
                ];
                continue;
            }

            $records[] = [
                'student_xgh' => $this->cell($row, 0),
                'student_name' => $this->cell($row, 1),
                'reason' => $this->cell($row, 2),
                'punished_at' => $this->date($this->cell($row, 3)),
                'annual_year' => $this->year($this->cell($row, 4)),
            ];
        }

        return $records;
    }

    private function cell(array $row, int $index): string
    {
        return trim((string) ($row[$index] ?? ''));
    }

    private function isBlankRow(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }

    private function year(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (preg_match('/(19|20)\d{2}/', $value, $matches)) {
            return (int) $matches[0];
        }

        return null;
    }

    private function date(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value))->toDateString();
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return $value;
        }
    }

    private function studentName(string $studentNumber, ?string $fallback): ?string
    {
        return Student::query()->where('xgh', $studentNumber)->value('xm') ?: $fallback;
    }
}
