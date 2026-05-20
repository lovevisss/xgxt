<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentAward;
use App\Models\StudentLoan;
use App\Models\StudentPunishment;
use App\Models\StudentSupportRecipient;
use App\Services\StudentImportWorkbook;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class StudentDataImportController extends Controller
{
    private const TYPES = ['award_punishment', 'loan', 'support'];

    public function page()
    {
        return view('student-data-import');
    }

    public function template(string $type, StudentImportWorkbook $workbook)
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        $path = storage_path("app/student-{$type}-template.xlsx");
        $workbook->write($path, $this->templateSheets($type));

        return Response::download($path, $this->templateName($type))->deleteFileAfterSend();
    }

    public function import(Request $request, string $type, StudentImportWorkbook $workbook)
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xls,xlsx', 'max:20480'],
            'annual_year' => ['nullable', 'integer', 'between:1900,2100'],
            'academic_year' => ['nullable', 'string', 'max:16'],
            'source' => ['nullable', 'string', 'max:64'],
        ]);

        $sheets = $workbook->read($request->file('file')->getRealPath());

        return match ($type) {
            'award_punishment' => response()->json($this->importAwardsAndPunishments($sheets)),
            'loan' => response()->json($this->importLoans($sheets, $request)),
            'support' => response()->json($this->importSupportRecipients($sheets, $request)),
        };
    }

    public function redirectPage()
    {
        return redirect()->route('student-imports.page');
    }

    public function redirectTemplate(string $type)
    {
        return redirect()->route('student-imports.template', ['type' => $type]);
    }

    private function importAwardsAndPunishments(array $sheets): array
    {
        $rewardRows = $this->sheetRows($sheets, '奖励') ?? [];
        $punishmentRows = $this->sheetRows($sheets, '惩罚') ?? [];
        $result = ['reward_imported' => 0, 'punishment_imported' => 0, 'errors' => []];

        DB::transaction(function () use ($rewardRows, $punishmentRows, &$result): void {
            foreach ($this->rewardRecords($rewardRows) as $index => $record) {
                $validator = Validator::make($record, [
                    'student_xgh' => ['required', 'string'],
                    'award_name' => ['required', 'string'],
                    'annual_year' => ['required', 'integer', 'between:1900,2100'],
                    'level' => ['nullable', 'string'],
                ]);

                if ($validator->fails()) {
                    $result['errors'][] = '奖励第 '.($index + 2).' 行：'.$validator->errors()->first();
                    continue;
                }

                StudentAward::query()->updateOrCreate(
                    [
                        'student_xgh' => $record['student_xgh'],
                        'award_name' => $record['award_name'],
                        'annual_year' => $record['annual_year'],
                        'level' => $record['level'],
                    ],
                    ['student_name' => $this->studentName($record['student_xgh'], $record['student_name']), 'imported_at' => now()]
                );
                $result['reward_imported']++;
            }

            foreach ($this->punishmentRecords($punishmentRows) as $index => $record) {
                $validator = Validator::make($record, [
                    'student_xgh' => ['required', 'string'],
                    'reason' => ['required', 'string'],
                    'punished_at' => ['nullable', 'date'],
                    'annual_year' => ['required', 'integer', 'between:1900,2100'],
                ]);

                if ($validator->fails()) {
                    $result['errors'][] = '惩罚第 '.($index + 2).' 行：'.$validator->errors()->first();
                    continue;
                }

                StudentPunishment::query()->updateOrCreate(
                    [
                        'student_xgh' => $record['student_xgh'],
                        'reason' => $record['reason'],
                        'punished_at' => $record['punished_at'],
                        'annual_year' => $record['annual_year'],
                    ],
                    ['student_name' => $this->studentName($record['student_xgh'], $record['student_name']), 'imported_at' => now()]
                );
                $result['punishment_imported']++;
            }
        });

        return $result;
    }

    private function importLoans(array $sheets, Request $request): array
    {
        $rows = $this->rowsWithHeader($sheets, ['学号', '金额']);
        $annualYear = $request->integer('annual_year') ?: $this->inferYear($rows);
        $source = trim((string) $request->input('source', '国开行')) ?: '国开行';
        $result = ['imported' => 0, 'errors' => []];

        DB::transaction(function () use ($rows, $annualYear, $source, &$result): void {
            foreach ($this->loanRecords($rows, $annualYear, $source) as $index => $record) {
                $validator = Validator::make($record, [
                    'student_xgh' => ['required', 'string'],
                    'amount' => ['required', 'numeric', 'min:0'],
                    'annual_year' => ['required', 'integer', 'between:1900,2100'],
                ]);

                if ($validator->fails()) {
                    $result['errors'][] = '第 '.($index + 1).' 条：'.$validator->errors()->first();
                    continue;
                }

                StudentLoan::query()->updateOrCreate(
                    ['student_xgh' => $record['student_xgh'], 'annual_year' => $record['annual_year'], 'source' => $record['source']],
                    [
                        'student_name' => $this->studentName($record['student_xgh'], $record['student_name']),
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

        return $result;
    }

    private function importSupportRecipients(array $sheets, Request $request): array
    {
        $rows = $this->rowsWithHeader($sheets, ['学号', '资助等级']);
        $academicYear = trim((string) $request->input('academic_year', '')) ?: $this->inferAcademicYear($rows);
        $result = ['imported' => 0, 'errors' => []];

        DB::transaction(function () use ($rows, $academicYear, &$result): void {
            foreach ($this->supportRecords($rows, $academicYear) as $index => $record) {
                $validator = Validator::make($record, [
                    'student_xgh' => ['required', 'string'],
                    'support_level' => ['required', 'string'],
                    'academic_year' => ['required', 'string'],
                ]);

                if ($validator->fails()) {
                    $result['errors'][] = '第 '.($index + 1).' 条：'.$validator->errors()->first();
                    continue;
                }

                StudentSupportRecipient::query()->updateOrCreate(
                    ['student_xgh' => $record['student_xgh'], 'academic_year' => $record['academic_year']],
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

        return $result;
    }

    private function rewardRecords(array $rows): array
    {
        $records = [];
        foreach (array_slice($rows, 1) as $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }
            $records[] = [
                'student_xgh' => $this->cell($row, 0),
                'student_name' => $this->cell($row, 1),
                'award_name' => $this->cell($row, 2),
                'annual_year' => $this->year($this->cell($row, 3)),
                'level' => $this->cell($row, 4),
            ];
        }

        return $records;
    }

    private function punishmentRecords(array $rows): array
    {
        $records = [];
        foreach (array_slice($rows, 1) as $row) {
            if ($this->isBlankRow($row)) {
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

    private function loanRecords(array $rows, ?int $annualYear, string $source): array
    {
        $header = $this->headerIndex($rows, ['学号', '金额']);
        if ($header === null) {
            return [['student_xgh' => '', 'amount' => null, 'annual_year' => $annualYear, 'source' => $source]];
        }

        $records = [];
        foreach (array_slice($rows, $header + 1) as $row) {
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

    private function supportRecords(array $rows, string $academicYear): array
    {
        $header = $this->headerIndex($rows, ['学号', '资助等级']);
        if ($header === null) {
            return [['student_xgh' => '', 'support_level' => '', 'academic_year' => $academicYear]];
        }

        $records = [];
        foreach (array_slice($rows, $header + 1) as $row) {
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

    private function templateSheets(string $type): array
    {
        return match ($type) {
            'award_punishment' => [
                '奖励' => [
                    ['学号', '姓名', '奖励名称', '年度', '等级'],
                    ['20260001', '张三', '全国大学生数学竞赛一等奖', '2026', '国家级'],
                    ['20260002', '李四', '优秀学生干部', '2026', '校级'],
                ],
                '惩罚' => [
                    ['学号', '姓名', '惩罚原因', '惩罚时间', '发生年度'],
                    ['20260003', '王五', '考试违纪', '2026-04-12', '2026'],
                    ['20260004', '赵六', '宿舍违规用电', '2026-05-01', '2026'],
                ],
            ],
            'loan' => [
                '助学贷款' => [
                    ['2025年生源地贷款到款名单汇总（示例）', '', '', '', '', '', '', ''],
                    ['序号', '身份证号码', '学号', '姓名', '二级学院', '班级', '金额', '备注'],
                    ['1', '320100200501010001', '20250001', '张三', '会计学院', '25会计1', '12000', '国开行'],
                    ['2', '320100200502020002', '20250002', '李四', '信息与人工智能学院', '25计算机1', '16000', '招商银行'],
                ],
            ],
            'support' => [
                '资助对象' => [
                    ['2025-2026学年浙江财经大学东方学院学生资助对象名单（示例）', '', '', '', '', '', ''],
                    ['序号', '学号', '姓名', '性别', '二级学院', '专业', '资助等级'],
                    ['1', '20250001', '张三', '女', '金融与经贸学院', '经济学', '重点'],
                    ['2', '20250002', '李四', '男', '信息与人工智能学院', '计算机科学与技术', '一般'],
                ],
            ],
        };
    }

    private function templateName(string $type): string
    {
        return match ($type) {
            'award_punishment' => '学生奖惩导入示例.xlsx',
            'loan' => '学生助学贷款导入示例.xlsx',
            'support' => '学生资助对象导入示例.xlsx',
        };
    }

    private function sheetRows(array $sheets, string $keyword): ?array
    {
        foreach ($sheets as $name => $rows) {
            if (str_contains($name, $keyword)) {
                return $rows;
            }
        }

        return null;
    }

    private function firstSheetRows(array $sheets): array
    {
        return array_values($sheets)[0] ?? [];
    }

    private function rowsWithHeader(array $sheets, array $requiredHeaders): array
    {
        foreach ($sheets as $rows) {
            if ($this->headerIndex($rows, $requiredHeaders) !== null) {
                return $rows;
            }
        }

        return $this->firstSheetRows($sheets);
    }

    private function headerIndex(array $rows, array $requiredHeaders): ?int
    {
        foreach ($rows as $index => $row) {
            $joined = implode('|', array_map(fn ($value) => trim((string) $value), $row));
            $matched = true;
            foreach ($requiredHeaders as $header) {
                if (! str_contains($joined, $header)) {
                    $matched = false;
                    break;
                }
            }
            if ($matched) {
                return $index;
            }
        }

        return null;
    }

    private function inferYear(array $rows): ?int
    {
        foreach (array_slice($rows, 0, 5) as $row) {
            if (preg_match('/(19|20)\d{2}/', implode(' ', $row), $matches)) {
                return (int) $matches[0];
            }
        }

        return null;
    }

    private function inferAcademicYear(array $rows): string
    {
        foreach (array_slice($rows, 0, 5) as $row) {
            if (preg_match('/(20\d{2})\s*[-—~至]\s*(20\d{2})/', implode(' ', $row), $matches)) {
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

    private function year(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return preg_match('/(19|20)\d{2}/', $value, $matches) ? (int) $matches[0] : null;
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

    private function amount(string $value): ?float
    {
        $normalized = str_replace([',', '，', '￥', '元', ' '], '', $value);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function studentName(string $studentNumber, ?string $fallback): ?string
    {
        return Student::query()->where('xgh', $studentNumber)->value('xm') ?: $fallback;
    }
}
