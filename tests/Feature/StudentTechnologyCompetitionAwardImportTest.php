<?php

use App\Models\Student;
use App\Models\StudentTechnologyCompetitionAward;
use App\Services\StudentImportWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

it('downloads the student technology competition award import template from unified import center', function () {
    $this->get('/student-imports/template/technology_competition_award')
        ->assertOk()
        ->assertHeader('content-disposition');
});

it('imports student technology competition award records from excel', function () {
    Student::query()->create([
        'xgh' => '20260071',
        'xm' => '竞赛学生',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '会计学院',
        'dwbm' => 'ACC',
        'bjmc' => '25会计1',
    ]);

    $path = storage_path('app/test-student-technology-competition-awards.xlsx');
    app(StudentImportWorkbook::class)->write($path, [
        'Sheet1' => [
            ['姓名', '学号', '学院', '班级', '年级', '荣誉名称', '时间'],
            ['导入姓名', '20260071', '会计学院', '25会计1', '2025', '第十一届浙江省大学生工程实践与创新能力大赛银奖', '2024-11-01 18:00:00'],
        ],
    ]);

    $file = new UploadedFile($path, 'technology-awards.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->postJson('/student-imports/technology_competition_award', [
        'file' => $file,
    ])
        ->assertOk()
        ->assertJsonPath('imported', 1);

    $this->assertDatabaseHas('student_technology_competition_awards', [
        'student_xgh' => '20260071',
        'student_name' => '竞赛学生',
        'college' => '会计学院',
        'class_name' => '25会计1',
        'grade' => '2025',
        'award_name' => '第十一届浙江省大学生工程实践与创新能力大赛银奖',
        'awarded_at' => '2024-11-01 18:00:00',
        'annual_year' => 2024,
    ]);
});

it('shows technology competition award records on the student profile', function () {
    Student::query()->create([
        'xgh' => '20260072',
        'xm' => '主页竞赛学生',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '会计学院',
        'dwbm' => 'ACC',
    ]);

    StudentTechnologyCompetitionAward::query()->create([
        'student_xgh' => '20260072',
        'student_name' => '主页竞赛学生',
        'college' => '会计学院',
        'class_name' => '25会计1',
        'grade' => '2025',
        'award_name' => '东方学院企业竞争模拟大赛二等奖',
        'awarded_at' => '2025-03-15 18:00:00',
        'annual_year' => 2025,
    ]);

    $this->get('/students/profile/20260072')
        ->assertOk()
        ->assertSee('"technologyCompetitionAwards":[', false)
        ->assertSee('"award_name":"\u4e1c\u65b9\u5b66\u9662\u4f01\u4e1a\u7ade\u4e89\u6a21\u62df\u5927\u8d5b\u4e8c\u7b49\u5956"', false)
        ->assertSee('"annual_year":2025', false);
});
