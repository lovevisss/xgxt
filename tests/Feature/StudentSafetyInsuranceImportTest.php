<?php

use App\Models\Student;
use App\Models\StudentSafetyInsurance;
use App\Services\StudentImportWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

it('downloads the student safety insurance import template from unified import center', function () {
    $this->get('/student-imports/template/safety_insurance')
        ->assertOk()
        ->assertHeader('content-disposition');
});

it('imports student safety insurance records from excel', function () {
    Student::query()->create([
        'xgh' => '20260041',
        'xm' => 'Safety Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '会计学院',
        'dwbm' => 'ACC',
    ]);

    $path = storage_path('app/test-student-safety-insurance.xlsx');
    app(StudentImportWorkbook::class)->write($path, [
        '导出学生基本信息列表' => [
            ['年级', '学制', '学院', '专业', '班级', '学号', '姓名', '是否参保'],
            ['2025级', '四年', '会计学院', '会计学', '25会计1', '20260041', 'Excel Name', '是'],
        ],
    ]);

    $file = new UploadedFile($path, 'student-safety-insurance.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->postJson('/student-imports/safety_insurance', [
        'file' => $file,
        'annual_year' => 2026,
    ])
        ->assertOk()
        ->assertJsonPath('imported', 1);

    $this->assertDatabaseHas('student_safety_insurances', [
        'student_xgh' => '20260041',
        'student_name' => 'Safety Student',
        'grade' => '2025级',
        'education_length' => '四年',
        'college' => '会计学院',
        'major' => '会计学',
        'class_name' => '25会计1',
        'annual_year' => 2026,
        'is_insured' => true,
    ]);
});

it('shows current year safety insurance status on the student profile', function () {
    $year = (int) now()->year;

    Student::query()->create([
        'xgh' => '20260042',
        'xm' => 'Profile Safety Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '会计学院',
        'dwbm' => 'ACC',
    ]);

    StudentSafetyInsurance::query()->create([
        'student_xgh' => '20260042',
        'student_name' => 'Profile Safety Student',
        'grade' => '2025级',
        'education_length' => '四年',
        'college' => '会计学院',
        'major' => '会计学',
        'class_name' => '25会计1',
        'annual_year' => $year,
        'is_insured' => true,
    ]);

    $this->get('/students/profile/20260042')
        ->assertOk()
        ->assertSee('"safetyInsurances":[', false)
        ->assertSee('"currentSafetyInsurance":', false)
        ->assertSee('"is_insured":true', false);
});
