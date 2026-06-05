<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentAward;
use App\Models\StudentCadreAssessmentMatch;
use App\Models\StudentLoan;
use App\Models\StudentMedicalInsurance;
use App\Models\StudentPhysicalTest;
use App\Models\StudentPunishment;
use App\Models\StudentSafetyInsurance;
use App\Models\StudentImportTask;
use App\Models\StudentSupportRecipient;
use App\Jobs\ImportStudentFamilyContacts;
use App\Services\StudentCadreAssessmentImportService;
use App\Services\StudentFamilyManualImportService;
use App\Services\StudentImportWorkbook;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Validator;

class StudentDataImportController extends Controller
{
    private const TYPES = ['award_punishment', 'loan', 'support', 'family', 'medical_insurance', 'safety_insurance', 'physical_test', 'cadre_assessment'];

    public function __construct(
        private readonly StudentFamilyManualImportService $familyImport,
        private readonly StudentCadreAssessmentImportService $cadreAssessmentImport,
    )
    {
    }

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

        if ($type === 'cadre_assessment') {
            @set_time_limit(600);
            @ini_set('max_execution_time', '600');

            $request->validate([
                'file' => ['required', 'file', 'mimes:pdf', 'max:51200'],
                'academic_year' => ['required', 'string', 'max:16'],
                'semester' => ['nullable', 'string', 'max:16'],
            ]);

            return response()->json($this->cadreAssessmentImport->import(
                $request->file('file'),
                trim((string) $request->input('academic_year')),
                $request->filled('semester') ? trim((string) $request->input('semester')) : null,
            ));
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:xls,xlsx', 'max:20480'],
            'annual_year' => ['nullable', 'integer', 'between:1900,2100'],
            'academic_year' => ['nullable', 'string', 'max:16'],
            'source' => ['nullable', 'string', 'max:64'],
        ]);

        if ($type === 'family') {
            if ($request->boolean('async')) {
                $path = $request->file('file')->store('student-imports');
                $task = StudentImportTask::query()->create([
                    'type' => 'family',
                    'status' => StudentImportTask::STATUS_QUEUED,
                    'original_name' => $request->file('file')->getClientOriginalName(),
                    'path' => $path,
                    'result' => ['imported' => 0, 'students' => 0, 'skipped' => 0, 'errors' => []],
                ]);

                ImportStudentFamilyContacts::dispatch($task->id);

                return response()->json([
                    'queued' => true,
                    'task_id' => $task->id,
                    'status' => $task->status,
                    'result' => $task->result,
                ], 202);
            }

            return response()->json($this->familyImport->import(
                $request->file('file')->getRealPath(),
                $request->file('file')->getClientOriginalExtension()
            ));
        }

        $sheets = $workbook->read($request->file('file')->getRealPath());

        return match ($type) {
            'award_punishment' => response()->json($this->importAwardsAndPunishments($sheets)),
            'loan' => response()->json($this->importLoans($sheets, $request)),
            'support' => response()->json($this->importSupportRecipients($sheets, $request)),
            'medical_insurance' => response()->json($this->importMedicalInsurances($sheets, $request)),
            'safety_insurance' => response()->json($this->importSafetyInsurances($sheets, $request)),
            'physical_test' => response()->json($this->importPhysicalTests($sheets, $request)),
        };
    }

    public function resolveCadreAssessmentMatch(Request $request, StudentCadreAssessmentMatch $match)
    {
        $data = $request->validate([
            'student_xgh' => ['required', 'string', 'exists:students,xgh'],
        ]);

        $assessment = $this->cadreAssessmentImport->resolve($match, $data['student_xgh']);

        return response()->json([
            'resolved' => true,
            'assessment' => $assessment,
        ]);
    }

    public function redirectPage()
    {
        return redirect()->route('student-imports.page');
    }

    public function status(StudentImportTask $task)
    {
        return response()->json([
            'id' => $task->id,
            'type' => $task->type,
            'status' => $task->status,
            'result' => $task->result ?? ['imported' => 0, 'students' => 0, 'skipped' => 0, 'errors' => []],
            'error' => $task->error,
            'started_at' => optional($task->started_at)->toIso8601String(),
            'finished_at' => optional($task->finished_at)->toIso8601String(),
        ]);
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

    private function importMedicalInsurances(array $sheets, Request $request): array
    {
        @set_time_limit(120);
        $this->ensureMedicalInsurancesTable();

        $rows = $this->rowsWithHeader($sheets, ['学号', '年度']);
        $annualYear = $request->integer('annual_year') ?: $this->inferYear($rows);
        $result = ['imported' => 0, 'errors' => []];
        $records = $this->medicalInsuranceRecords($rows, $annualYear);
        $validRecords = [];

        foreach ($records as $index => $record) {
            $validator = Validator::make($record, [
                'student_xgh' => ['required', 'string'],
                'annual_year' => ['required', 'integer', 'between:1900,2100'],
            ]);

            if ($validator->fails()) {
                $result['errors'][] = '第'.($index + 1).' 条：'.$validator->errors()->first();
                continue;
            }

            $validRecords[] = $record;
        }

        if ($validRecords === []) {
            return $result;
        }

        $studentNames = Student::query()
            ->whereIn('xgh', collect($validRecords)->pluck('student_xgh')->unique()->values())
            ->pluck('xm', 'xgh');
        $now = now();

        DB::transaction(function () use ($validRecords, $studentNames, $now, &$result): void {
            foreach (array_chunk($validRecords, 1000) as $chunk) {
                $payload = array_map(function (array $record) use ($studentNames, $now): array {
                    return [
                        'student_xgh' => $record['student_xgh'],
                        'student_name' => $studentNames[$record['student_xgh']] ?? $record['student_name'],
                        'insured_area' => $record['insured_area'],
                        'enrolled_on' => $record['enrolled_on'],
                        'insurance_type' => $record['insurance_type'],
                        'insurance_status' => $record['insurance_status'],
                        'identity_type' => $record['identity_type'],
                        'annual_year' => $record['annual_year'],
                        'has_paid' => $record['has_paid'],
                        'payment_start_month' => $record['payment_start_month'],
                        'payment_end_month' => $record['payment_end_month'],
                        'payment_type' => $record['payment_type'],
                        'imported_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $chunk);

                StudentMedicalInsurance::query()->upsert(
                    $payload,
                    ['student_xgh', 'annual_year'],
                    [
                        'student_name',
                        'insured_area',
                        'enrolled_on',
                        'insurance_type',
                        'insurance_status',
                        'identity_type',
                        'has_paid',
                        'payment_start_month',
                        'payment_end_month',
                        'payment_type',
                        'imported_at',
                        'updated_at',
                    ]
                );

                $result['imported'] += count($payload);
            }
        });

        return $result;
    }

    private function importSafetyInsurances(array $sheets, Request $request): array
    {
        @set_time_limit(120);
        $this->ensureSafetyInsurancesTable();

        $rows = $this->rowsWithHeader($sheets, ['学号', '是否参保']);
        $annualYear = $request->integer('annual_year') ?: $this->inferYear($rows);
        $result = ['imported' => 0, 'errors' => []];
        $records = $this->safetyInsuranceRecords($rows, $annualYear);
        $validRecords = [];

        foreach ($records as $index => $record) {
            $validator = Validator::make($record, [
                'student_xgh' => ['required', 'string'],
                'annual_year' => ['required', 'integer', 'between:1900,2100'],
            ]);

            if ($validator->fails()) {
                $result['errors'][] = '第'.($index + 1).' 条：'.$validator->errors()->first();
                continue;
            }

            $validRecords[] = $record;
        }

        if ($validRecords === []) {
            return $result;
        }

        $studentNames = Student::query()
            ->whereIn('xgh', collect($validRecords)->pluck('student_xgh')->unique()->values())
            ->pluck('xm', 'xgh');
        $now = now();

        DB::transaction(function () use ($validRecords, $studentNames, $now, &$result): void {
            foreach (array_chunk($validRecords, 1000) as $chunk) {
                $payload = array_map(function (array $record) use ($studentNames, $now): array {
                    return [
                        'student_xgh' => $record['student_xgh'],
                        'student_name' => $studentNames[$record['student_xgh']] ?? $record['student_name'],
                        'grade' => $record['grade'],
                        'education_length' => $record['education_length'],
                        'college' => $record['college'],
                        'major' => $record['major'],
                        'class_name' => $record['class_name'],
                        'annual_year' => $record['annual_year'],
                        'is_insured' => $record['is_insured'],
                        'imported_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $chunk);

                StudentSafetyInsurance::query()->upsert(
                    $payload,
                    ['student_xgh', 'annual_year'],
                    [
                        'student_name',
                        'grade',
                        'education_length',
                        'college',
                        'major',
                        'class_name',
                        'is_insured',
                        'imported_at',
                        'updated_at',
                    ]
                );

                $result['imported'] += count($payload);
            }
        });

        return $result;
    }

    private function importPhysicalTests(array $sheets, Request $request): array
    {
        @set_time_limit(120);
        $this->ensurePhysicalTestsTable();

        $rows = $this->rowsWithHeader($sheets, ['学号', '总分']);
        $fallbackAcademicYear = trim((string) $request->input('academic_year', '')) ?: $this->inferAcademicYear($rows);
        $result = ['imported' => 0, 'errors' => []];
        $records = $this->physicalTestRecords($rows, $fallbackAcademicYear);
        $validRecords = [];

        foreach ($records as $index => $record) {
            $validator = Validator::make($record, [
                'student_xgh' => ['required', 'string'],
                'academic_year' => ['required', 'string', 'max:16'],
                'score' => ['nullable', 'numeric', 'between:0,100'],
            ]);

            if ($validator->fails()) {
                $result['errors'][] = '第'.($index + 1).' 条：'.$validator->errors()->first();
                continue;
            }

            $validRecords[] = $record;
        }

        if ($validRecords === []) {
            return $result;
        }

        $studentNames = Student::query()
            ->whereIn('xgh', collect($validRecords)->pluck('student_xgh')->unique()->values())
            ->pluck('xm', 'xgh');
        $now = now();

        DB::transaction(function () use ($validRecords, $studentNames, $now, &$result): void {
            foreach (array_chunk($validRecords, 1000) as $chunk) {
                $payload = array_map(function (array $record) use ($studentNames, $now): array {
                    return [
                        'student_xgh' => $record['student_xgh'],
                        'student_name' => $studentNames[$record['student_xgh']] ?? $record['student_name'],
                        'gender' => $record['gender'],
                        'college' => $record['college'],
                        'class_name' => $record['class_name'],
                        'academic_year' => $record['academic_year'],
                        'score' => $record['score'],
                        'remark' => $record['remark'],
                        'imported_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $chunk);

                StudentPhysicalTest::query()->upsert(
                    $payload,
                    ['student_xgh', 'academic_year'],
                    [
                        'student_name',
                        'gender',
                        'college',
                        'class_name',
                        'score',
                        'remark',
                        'imported_at',
                        'updated_at',
                    ]
                );

                $result['imported'] += count($payload);
            }
        });

        return $result;
    }

    private function ensureMedicalInsurancesTable(): void
    {
        if (Schema::hasTable('student_medical_insurances')) {
            return;
        }

        Schema::create('student_medical_insurances', function (Blueprint $table) {
            $table->id();
            $table->string('student_xgh')->index();
            $table->string('student_name')->nullable();
            $table->string('insured_area')->nullable()->index();
            $table->date('enrolled_on')->nullable();
            $table->string('insurance_type')->nullable();
            $table->string('insurance_status')->nullable()->index();
            $table->string('identity_type')->nullable();
            $table->unsignedSmallInteger('annual_year')->index();
            $table->boolean('has_paid')->default(false)->index();
            $table->string('payment_start_month', 6)->nullable();
            $table->string('payment_end_month', 6)->nullable();
            $table->string('payment_type')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['student_xgh', 'annual_year'], 'uniq_student_medical_student_year');
        });
    }

    private function ensureSafetyInsurancesTable(): void
    {
        if (Schema::hasTable('student_safety_insurances')) {
            return;
        }

        Schema::create('student_safety_insurances', function (Blueprint $table) {
            $table->id();
            $table->string('student_xgh')->index();
            $table->string('student_name')->nullable();
            $table->string('grade')->nullable()->index();
            $table->string('education_length')->nullable();
            $table->string('college')->nullable()->index();
            $table->string('major')->nullable();
            $table->string('class_name')->nullable()->index();
            $table->unsignedSmallInteger('annual_year')->index();
            $table->boolean('is_insured')->default(false)->index();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['student_xgh', 'annual_year'], 'uniq_student_safety_student_year');
        });
    }

    private function ensurePhysicalTestsTable(): void
    {
        if (Schema::hasTable('student_physical_tests')) {
            return;
        }

        Schema::create('student_physical_tests', function (Blueprint $table) {
            $table->id();
            $table->string('student_xgh')->index();
            $table->string('student_name')->nullable();
            $table->string('gender')->nullable();
            $table->string('college')->nullable()->index();
            $table->string('class_name')->nullable()->index();
            $table->string('academic_year', 16)->index();
            $table->decimal('score', 5, 1)->nullable();
            $table->string('remark')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['student_xgh', 'academic_year'], 'uniq_student_physical_student_year');
        });
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

    private function medicalInsuranceRecords(array $rows, ?int $annualYear): array
    {
        $header = $this->headerIndex($rows, ['学号', '年度']);
        if ($header === null) {
            return [['student_xgh' => '', 'annual_year' => $annualYear]];
        }

        $headers = $rows[$header] ?? [];
        $records = [];
        foreach (array_slice($rows, $header + 1) as $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $year = $this->year($this->cellByHeader($row, $headers, ['年度'])) ?: $annualYear;
            $records[] = [
                'student_xgh' => $this->cellByHeader($row, $headers, ['学号']),
                'student_name' => $this->cellByHeader($row, $headers, ['姓名']),
                'insured_area' => $this->cellByHeader($row, $headers, ['参保地']),
                'enrolled_on' => $this->date($this->cellByHeader($row, $headers, ['参保日期'])),
                'insurance_type' => $this->cellByHeader($row, $headers, ['险种']),
                'insurance_status' => $this->cellByHeader($row, $headers, ['参保状态']),
                'identity_type' => $this->cellByHeader($row, $headers, ['城居参保身份', '参保身份']),
                'annual_year' => $year,
                'has_paid' => $this->truthy($this->cellByHeader($row, $headers, ['年度是否缴费', '是否缴费'])),
                'payment_start_month' => $this->month($this->cellByHeader($row, $headers, ['缴费开始年月'])),
                'payment_end_month' => $this->month($this->cellByHeader($row, $headers, ['缴费结束年月'])),
                'payment_type' => $this->cellByHeader($row, $headers, ['缴费类型']),
            ];
        }

        return $records;
    }

    private function safetyInsuranceRecords(array $rows, ?int $annualYear): array
    {
        $header = $this->headerIndex($rows, ['学号', '是否参保']);
        if ($header === null) {
            return [['student_xgh' => '', 'annual_year' => $annualYear]];
        }

        $headers = $rows[$header] ?? [];
        $records = [];
        foreach (array_slice($rows, $header + 1) as $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $records[] = [
                'student_xgh' => $this->cellByHeader($row, $headers, ['学号']),
                'student_name' => $this->cellByHeader($row, $headers, ['姓名']),
                'grade' => $this->cellByHeader($row, $headers, ['年级']),
                'education_length' => $this->cellByHeader($row, $headers, ['学制']),
                'college' => $this->cellByHeader($row, $headers, ['学院']),
                'major' => $this->cellByHeader($row, $headers, ['专业']),
                'class_name' => $this->cellByHeader($row, $headers, ['班级']),
                'annual_year' => $annualYear,
                'is_insured' => $this->truthy($this->cellByHeader($row, $headers, ['是否参保'])),
            ];
        }

        return $records;
    }

    private function physicalTestRecords(array $rows, string $fallbackAcademicYear): array
    {
        $header = $this->headerIndex($rows, ['学号', '总分']);
        if ($header === null) {
            return [['student_xgh' => '', 'academic_year' => $fallbackAcademicYear]];
        }

        $headers = $rows[$header] ?? [];
        $records = [];
        foreach (array_slice($rows, $header + 1) as $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $academicYear = $this->academicYear($this->cellByHeader($row, $headers, ['学年'])) ?: $fallbackAcademicYear;
            $records[] = [
                'student_xgh' => $this->cellByHeader($row, $headers, ['学号']),
                'student_name' => $this->cellByHeader($row, $headers, ['姓名']),
                'gender' => $this->cellByHeader($row, $headers, ['性别']),
                'college' => $this->cellByHeader($row, $headers, ['院系', '学院']),
                'class_name' => $this->cellByHeader($row, $headers, ['班级']),
                'academic_year' => $academicYear,
                'score' => $this->score($this->cellByHeader($row, $headers, ['总分'])),
                'remark' => $this->cellByHeader($row, $headers, ['备注']),
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
            'family' => [
                '家长信息' => [
                    ['学号', '姓名', '学院', '专业', '班级', '身份证号', '性别', '民族', '政治面貌', '籍贯', '入学前户口', '本人联系手机号', '家庭座机号', 'QQ号', '家庭住址', '户籍地址', '身高', '体重', '家庭成员称谓1', '姓名1', '工作单位1', '职务1', '联系电话1', '家庭成员称谓2', '姓名2', '工作单位2', '职务2', '联系电话2', '家庭成员称谓3', '姓名3', '工作单位3', '职务3', '联系电话3', '家庭成员称谓4', '姓名4', '工作单位4', '职务4', '联系电话4', '备注'],
                    ['20230001', '张三', '会计学院', '会计学', '23会计1', '330100200501010001', '男', '汉族', '共青团员', '浙江杭州', '城镇', '13800000000', '', '', '浙江省杭州市', '浙江省杭州市', '175', '65', '父亲', '张建国', '杭州某公司', '经理', '13900000001', '母亲', '李芳', '杭州某学校', '教师', '13900000002', '', '', '', '', '', '', '', '', '', '', ''],
                    ['20230002', '李四', '金融与经贸学院', '经济学', '23经济1', '330100200502020002', '女', '汉族', '群众', '浙江宁波', '农村', '13800000003', '', '', '浙江省宁波市', '浙江省宁波市', '162', '50', '父亲', '李明', '个体经营', '', '13900000003', '母亲', '王丽', '', '', '13900000004', '父亲', '李明', '个体经营', '', '13900000003', '', '', '', '', '', '重复联系人会自动去重'],
                ],
            ],
            'medical_insurance' => [
                '参保缴费名单' => [
                    ['姓名', '学号', '参保地', '参保日期', '险种', '参保状态', '城居参保身份', '年度', '年度是否缴费', '缴费开始年月', '缴费结束年月', '缴费类型'],
                    ['张三', '20260001', '西湖区', '2026-01-01', '城乡居民基本医疗保险', '正常参保', '大学生', '2026年度', '是', '202601', '202612', '足额缴纳'],
                    ['李四', '20260002', '西湖区', '2026-01-01', '城乡居民基本医疗保险', '正常参保', '大学生', '2026年度', '是', '202601', '202612', '足额缴纳'],
                ],
            ],
            'safety_insurance' => [
                '学平险参保名单' => [
                    ['年级', '学制', '学院', '专业', '班级', '学号', '姓名', '是否参保'],
                    ['2025级', '四年', '会计学院', '会计学', '25会计1', '20260001', '张三', '是'],
                    ['2025级', '四年', '金融与经贸学院', '经济学', '25经济1', '20260002', '李四', '否'],
                ],
            ],
            'physical_test' => [
                '体测成绩' => [
                    ['学年', '姓名', '学号', '性别', '院系', '班级', '总分', '备注'],
                    ['2023-2024学年', '张三', '20260001', '男', '会计学院', '25会计1', '82.5', ''],
                    ['2023-2024学年', '李四', '20260002', '女', '金融与经贸学院', '25经济1', '76.4', ''],
                ],
            ],
            'cadre_assessment' => [
                '导入说明' => [
                    ['请直接上传团学干部考核成绩汇总 PDF'],
                    ['系统会按姓名自动匹配学生；同名无法区分的记录会进入待确认。'],
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
            'family' => '学生家长信息导入示例.xlsx',
            'medical_insurance' => '大学生医保参保缴费导入示例.xlsx',
            'safety_insurance' => '大学生学平险参保导入示例.xlsx',
            'physical_test' => '学生体测成绩导入示例.xlsx',
            'cadre_assessment' => '团学干部考核导入说明.xlsx',
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

    private function cellByHeader(array $row, array $headers, array $candidates): string
    {
        foreach ($headers as $index => $header) {
            $normalized = trim((string) $header);
            foreach ($candidates as $candidate) {
                if ($normalized === $candidate || str_contains($normalized, $candidate)) {
                    return $this->cell($row, $index);
                }
            }
        }

        return '';
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

    private function truthy(string $value): bool
    {
        $normalized = mb_strtolower(trim($value));

        return in_array($normalized, ['1', 'true', 'yes', 'y', '是', '已缴费', '已参保', '正常参保'], true);
    }

    private function month(string $value): ?string
    {
        $normalized = preg_replace('/\D/', '', $value);

        return strlen((string) $normalized) >= 6 ? substr((string) $normalized, 0, 6) : null;
    }

    private function academicYear(string $value): string
    {
        if (preg_match('/(20\d{2})\s*[-—~至]\s*(20\d{2})/', $value, $matches)) {
            return $matches[1].'-'.$matches[2];
        }

        return trim(str_replace('学年', '', $value));
    }

    private function score(string $value): ?float
    {
        $normalized = str_replace([',', '分', ' '], '', $value);

        return is_numeric($normalized) ? round((float) $normalized, 1) : null;
    }

    private function studentName(string $studentNumber, ?string $fallback): ?string
    {
        return Student::query()->where('xgh', $studentNumber)->value('xm') ?: $fallback;
    }
}
