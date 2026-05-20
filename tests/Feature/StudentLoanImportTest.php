<?php

use App\Models\Student;
use App\Models\StudentLoan;
use App\Services\StudentImportWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

it('downloads the student loan import template from unified import center', function () {
    $this->get('/student-imports/template/loan')
        ->assertOk()
        ->assertHeader('content-disposition');
});

it('imports student loans from xlsx', function () {
    Student::query()->create([
        'xgh' => '20250001',
        'xm' => 'Loan Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '会计学院',
        'dwbm' => 'ACC',
        'bjmc' => '25会计1',
    ]);

    $path = storage_path('app/test-student-loans.xlsx');
    app(StudentImportWorkbook::class)->write($path, [
        '助学贷款' => [
            ['2025年生源地贷款到款名单汇总（截止11月28日）', '', '', '', '', '', '', ''],
            ['序号', '身份证号码', '学号', '姓名', '二级学院', '班级', '金额', '备注'],
            ['1', '320100200501010001', '20250001', 'Excel Name', '会计学院', '25会计1', '12000', '招商银行'],
        ],
    ]);

    $file = new UploadedFile($path, 'student-loans.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->postJson('/student-imports/loan', [
        'file' => $file,
        'annual_year' => 2025,
        'source' => '国开行',
    ])
        ->assertOk()
        ->assertJsonPath('imported', 1);

    $this->assertDatabaseHas('student_loans', [
        'student_xgh' => '20250001',
        'student_name' => 'Loan Student',
        'id_card' => '320100200501010001',
        'college' => '会计学院',
        'class_name' => '25会计1',
        'amount' => 12000,
        'annual_year' => 2025,
        'source' => '国开行',
        'remark' => '招商银行',
    ]);
});

it('shows student loan records on the student profile', function () {
    Student::query()->create([
        'xgh' => '20250002',
        'xm' => 'Profile Loan Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '会计学院',
        'dwbm' => 'ACC',
    ]);

    StudentLoan::query()->create([
        'student_xgh' => '20250002',
        'student_name' => 'Profile Loan Student',
        'id_card' => '320100200502020002',
        'college' => '会计学院',
        'class_name' => '25会计2',
        'amount' => 16000,
        'annual_year' => 2025,
        'source' => '国开行',
        'remark' => '招商银行',
    ]);

    $this->get('/students/profile/20250002')
        ->assertOk()
        ->assertSee('"loans":[', false)
        ->assertSee('"amount":"16000.00"', false);
});
