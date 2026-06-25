<?php

use App\Models\Student;
use App\Models\StudentMoralAssessment;
use App\Services\StudentImportWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

it('downloads the student moral assessment import template from unified import center', function () {
    $this->get('/student-imports/template/moral_assessment')
        ->assertOk()
        ->assertHeader('content-disposition');
});

it('imports student moral assessment records from excel by semester and updates duplicates', function () {
    Student::query()->create([
        'xgh' => '20260081',
        'xm' => '德育学生一',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '文化传播与设计学院',
        'dwbm' => 'ART',
        'bjmc' => '25汉语言文学1班',
    ]);
    Student::query()->create([
        'xgh' => '20260082',
        'xm' => '德育学生二',
        'xbm' => '2',
        'rylx' => '0',
        'dwmc' => '文化传播与设计学院',
        'dwbm' => 'ART',
        'bjmc' => '24汉语言文学1班',
    ]);

    $path = storage_path('app/test-student-moral-assessments.xlsx');
    app(StudentImportWorkbook::class)->write($path, [
        '2025级' => [
            ['浙江财经大学东方学院学生德育记实量化考核成绩汇总表', '', '', '', '', '', '', ''],
            ['（2025——2026学年第一学期）', '', '', '', '', '', '', ''],
            ['二级学院：文化传播与设计学院 班级：25汉语言文学1班 辅导员签名：      二级学院公章：', '', '', '', '', '', '', ''],
            ['名次', '学号', '姓名', '德育分', '', '', '德育总分', '备注'],
            ['', '', '', '基础分', '加分', '减分', '', ''],
            [1, '20260081', '导入学生一', 70, 58.4, 0, 128.4, '优秀'],
        ],
        '2024级' => [
            ['浙江财经大学东方学院学生德育记实量化考核成绩汇总表', '', '', '', '', '', '', ''],
            ['（ 2025  —— 2026  学年第 一 学期）', '', '', '', '', '', '', ''],
            ['二级学院：文化传播与设计学院 班级：24汉语言文学1班 辅导员签名：      二级学院公章：', '', '', '', '', '', '', ''],
            ['名次', '学号', '姓名', '德育分', '', '', '德育总分', '备注'],
            ['', '', '', '基础分', '加分', '减分', '', ''],
            [2, 20260082, '导入学生二', 70, 24, -1, 93, ''],
        ],
    ]);

    $file = new UploadedFile($path, 'student-moral-assessments.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->postJson('/student-imports/moral_assessment', [
        'file' => $file,
    ])
        ->assertOk()
        ->assertJsonPath('imported', 2);

    $this->assertDatabaseHas('student_moral_assessments', [
        'student_xgh' => '20260081',
        'student_name' => '德育学生一',
        'academic_year' => '2025-2026',
        'semester' => '1',
        'college' => '文化传播与设计学院',
        'class_name' => '25汉语言文学1班',
        'rank' => 1,
        'base_score' => 70,
        'bonus_score' => 58.4,
        'deduction_score' => 0,
        'total_score' => 128.4,
        'remark' => '优秀',
    ]);
    $this->assertDatabaseHas('student_moral_assessments', [
        'student_xgh' => '20260082',
        'academic_year' => '2025-2026',
        'semester' => '1',
        'total_score' => 93,
    ]);

    app(StudentImportWorkbook::class)->write($path, [
        '2025级' => [
            ['浙江财经大学东方学院学生德育记实量化考核成绩汇总表', '', '', '', '', '', '', ''],
            ['（2025——2026学年第一学期）', '', '', '', '', '', '', ''],
            ['二级学院：文化传播与设计学院 班级：25汉语言文学1班 辅导员签名：      二级学院公章：', '', '', '', '', '', '', ''],
            ['名次', '学号', '姓名', '德育分', '', '', '德育总分', '备注'],
            ['', '', '', '基础分', '加分', '减分', '', ''],
            [1, '20260081', '导入学生一', 70, 60, 0, 130, '更新'],
        ],
    ]);
    $updatedFile = new UploadedFile($path, 'student-moral-assessments-updated.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->postJson('/student-imports/moral_assessment', [
        'file' => $updatedFile,
    ])
        ->assertOk()
        ->assertJsonPath('imported', 1);

    $this->assertDatabaseCount('student_moral_assessments', 2);
    $this->assertDatabaseHas('student_moral_assessments', [
        'student_xgh' => '20260081',
        'academic_year' => '2025-2026',
        'semester' => '1',
        'total_score' => 130,
        'remark' => '更新',
    ]);
});

it('shows moral assessment records on the student profile', function () {
    Student::query()->create([
        'xgh' => '20260083',
        'xm' => '主页德育学生',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '文化传播与设计学院',
        'dwbm' => 'ART',
    ]);

    StudentMoralAssessment::query()->create([
        'student_xgh' => '20260083',
        'student_name' => '主页德育学生',
        'academic_year' => '2025-2026',
        'semester' => '1',
        'college' => '文化传播与设计学院',
        'class_name' => '25汉语言文学1班',
        'rank' => 3,
        'base_score' => 70,
        'bonus_score' => 33.5,
        'deduction_score' => 0,
        'total_score' => 103.5,
        'remark' => '稳定',
    ]);

    $this->get('/students/profile/20260083')
        ->assertOk()
        ->assertSee('"moralAssessments":[', false)
        ->assertSee('"academic_year":"2025-2026"', false)
        ->assertSee('"semester":"1"', false)
        ->assertSee('"total_score":103.5', false);
});
