<?php

use App\Models\Student;
use App\Models\StudentComprehensiveAssessment;
use App\Services\StudentImportWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

it('downloads the student comprehensive assessment import template from unified import center', function () {
    $this->get('/student-imports/template/comprehensive_assessment')
        ->assertOk()
        ->assertHeader('content-disposition');
});

it('imports student comprehensive assessment records from excel', function () {
    Student::query()->create([
        'xgh' => '20260061',
        'xm' => '综测学生',
        'rylx' => '0',
        'dwmc' => '会计学院',
        'dwbm' => 'ACC',
        'bjmc' => '22会计1班',
    ]);

    $path = storage_path('app/test-student-comprehensive-assessment.xlsx');
    app(StudentImportWorkbook::class)->write($path, [
        '2022级' => [
            ['浙江财经大学东方学院学生综合测评成绩汇总表', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['（2024——2025学年）', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['二级学院：会计学院 班级：22会计1班 辅导员签名：', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['名次', '姓   名', '学  号', '综合测评成绩', '德育分', '', '', '', '智育分', '', '', '', '体育分', '', '', '', '美育分', '', '', '劳育分', '', ''],
            ['', '', '', '', '基础分', '加分', '减分', '总分', '平均成绩', '加分', '减分', '总分', '体育成绩', '加分', '减分', '总分', '美育成绩', '加分', '总分', '劳育成绩', '加分', '总分'],
            ['', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['1', '综测学生', '20260061', '94.28', '70', '30', '0', '100', '93.68', '3', '0', '96.68', '80.8', '0', '0', '80.8', '70', '22.8', '92.8', '70', '15.8', '85.8'],
        ],
    ]);

    $file = new UploadedFile($path, 'student-comprehensive-assessment.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->postJson('/student-imports/comprehensive_assessment', [
        'file' => $file,
    ])
        ->assertOk()
        ->assertJsonPath('imported', 1);

    $this->assertDatabaseHas('student_comprehensive_assessments', [
        'student_xgh' => '20260061',
        'student_name' => '综测学生',
        'academic_year' => '2024-2025',
        'college' => '会计学院',
        'class_name' => '22会计1班',
        'rank' => 1,
        'total_score' => 94.28,
        'moral_score' => 100,
        'intellectual_score' => 96.68,
        'physical_score' => 80.8,
        'aesthetic_score' => 92.8,
        'labor_score' => 85.8,
    ]);
});

it('shows comprehensive assessment records on the student profile', function () {
    Student::query()->create([
        'xgh' => '20260062',
        'xm' => '个人页综测学生',
        'rylx' => '0',
        'dwmc' => '会计学院',
        'dwbm' => 'ACC',
    ]);

    StudentComprehensiveAssessment::query()->create([
        'student_xgh' => '20260062',
        'student_name' => '个人页综测学生',
        'academic_year' => '2024-2025',
        'college' => '会计学院',
        'class_name' => '22会计1班',
        'rank' => 2,
        'total_score' => 91.68,
        'moral_score' => 95.15,
        'intellectual_score' => 99.5,
        'physical_score' => 82.5,
        'aesthetic_score' => 75.5,
        'labor_score' => 86,
    ]);

    $this->get('/students/profile/20260062')
        ->assertOk()
        ->assertSee('"comprehensiveAssessments":[', false)
        ->assertSee('"academic_year":"2024-2025"', false)
        ->assertSee('"total_score":91.68', false)
        ->assertSee('"rank":2', false);
});
