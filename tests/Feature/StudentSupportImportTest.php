<?php

use App\Models\Student;
use App\Models\StudentSupportRecipient;
use App\Services\StudentImportWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

it('downloads the student support import template from unified import center', function () {
    $this->get('/student-imports/template/support')
        ->assertOk()
        ->assertHeader('content-disposition');
});

it('imports student support recipients from excel', function () {
    Student::query()->create([
        'xgh' => '20260021',
        'xm' => 'Support Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '金融与经贸学院',
        'dwbm' => 'FIN',
    ]);

    $path = storage_path('app/test-student-support.xlsx');
    app(StudentImportWorkbook::class)->write($path, [
        '资助对象' => [
            ['2025-2026学年浙江财经大学东方学院学生资助对象名单（示例）', '', '', '', '', '', ''],
            ['序号', '学号', '姓名', '性别', '二级学院', '专业', '资助等级'],
            ['1', '20260021', 'Excel Name', '女', '金融与经贸学院', '经济学', '重点'],
        ],
    ]);

    $file = new UploadedFile($path, 'student-support.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->postJson('/student-imports/support', [
        'file' => $file,
        'academic_year' => '2025-2026',
    ])
        ->assertOk()
        ->assertJsonPath('imported', 1);

    $this->assertDatabaseHas('student_support_recipients', [
        'student_xgh' => '20260021',
        'student_name' => 'Support Student',
        'gender' => '女',
        'college' => '金融与经贸学院',
        'major' => '经济学',
        'support_level' => '重点',
        'academic_year' => '2025-2026',
    ]);
});

it('shows support recipient records on the student profile', function () {
    Student::query()->create([
        'xgh' => '20260022',
        'xm' => 'Profile Support Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '金融与经贸学院',
        'dwbm' => 'FIN',
    ]);

    StudentSupportRecipient::query()->create([
        'student_xgh' => '20260022',
        'student_name' => 'Profile Support Student',
        'gender' => '男',
        'college' => '金融与经贸学院',
        'major' => '经济学',
        'support_level' => '一般',
        'academic_year' => '2025-2026',
    ]);

    $this->get('/students/profile/20260022')
        ->assertOk()
        ->assertSee('"supportRecipients":[', false)
        ->assertSee('"support_level":"\u4e00\u822c"', false);
});
