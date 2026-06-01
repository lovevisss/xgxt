<?php

use App\Models\Student;
use App\Models\StudentPhysicalTest;
use App\Services\StudentImportWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

it('downloads the student physical test import template from unified import center', function () {
    $this->get('/student-imports/template/physical_test')
        ->assertOk()
        ->assertHeader('content-disposition');
});

it('imports student physical test records from excel', function () {
    Student::query()->create([
        'xgh' => '20260051',
        'xm' => 'Physical Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '会计学院',
        'dwbm' => 'ACC',
    ]);

    $path = storage_path('app/test-student-physical-test.xlsx');
    app(StudentImportWorkbook::class)->write($path, [
        'Sheet1' => [
            ['学年', '姓名', '学号', '性别', '院系', '班级', '总分', '备注'],
            ['2023-2024学年', 'Excel Name', '20260051', '男', '会计学院', '25会计1', '82.5', '补测通过'],
        ],
    ]);

    $file = new UploadedFile($path, 'student-physical-test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->postJson('/student-imports/physical_test', [
        'file' => $file,
    ])
        ->assertOk()
        ->assertJsonPath('imported', 1);

    $this->assertDatabaseHas('student_physical_tests', [
        'student_xgh' => '20260051',
        'student_name' => 'Physical Student',
        'gender' => '男',
        'college' => '会计学院',
        'class_name' => '25会计1',
        'academic_year' => '2023-2024',
        'score' => 82.5,
        'remark' => '补测通过',
    ]);
});

it('shows physical test records on the student profile', function () {
    Student::query()->create([
        'xgh' => '20260052',
        'xm' => 'Profile Physical Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '会计学院',
        'dwbm' => 'ACC',
    ]);

    StudentPhysicalTest::query()->create([
        'student_xgh' => '20260052',
        'student_name' => 'Profile Physical Student',
        'gender' => '女',
        'college' => '会计学院',
        'class_name' => '25会计1',
        'academic_year' => '2023-2024',
        'score' => 91.2,
        'remark' => '优秀',
    ]);

    $this->get('/students/profile/20260052')
        ->assertOk()
        ->assertSee('"physicalTests":[', false)
        ->assertSee('"score":"91.2"', false)
        ->assertSee('"academic_year":"2023-2024"', false);
});
