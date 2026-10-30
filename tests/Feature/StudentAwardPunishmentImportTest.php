<?php

use App\Models\Student;
use App\Models\StudentAward;
use App\Models\StudentPunishment;
use App\Services\StudentImportWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

it('downloads the award and punishment import template from unified import center', function () {
    $this->get('/student-imports/template/award_punishment')
        ->assertOk()
        ->assertHeader('content-disposition');
});

it('imports student awards and punishments from xlsx', function () {
    Student::query()->create([
        'xgh' => '20260011',
        'xm' => 'Award Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => 'Test College',
        'dwbm' => 'TEST',
    ]);

    Student::query()->create([
        'xgh' => '20260012',
        'xm' => 'Punish Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => 'Test College',
        'dwbm' => 'TEST',
    ]);

    $path = storage_path('app/test-award-punishment.xlsx');
    app(StudentImportWorkbook::class)->write($path, [
        '奖励' => [
            ['学号', '姓名', '奖励名称', '年度', '等级'],
            ['20260011', 'Import Name', '优秀学生奖学金', '2026', '校级'],
        ],
        '惩罚' => [
            ['学号', '姓名', '惩罚原因', '惩罚时间', '发生年度'],
            ['20260012', 'Import Name', '考试违纪', '2026-05-01', '2026'],
        ],
    ]);

    $file = new UploadedFile($path, 'test-award-punishment.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->postJson('/student-imports/award_punishment', ['file' => $file])
        ->assertOk()
        ->assertJsonPath('reward_imported', 1)
        ->assertJsonPath('punishment_imported', 1);

    $this->assertDatabaseHas('student_awards', [
        'student_xgh' => '20260011',
        'student_name' => 'Award Student',
        'award_name' => '优秀学生奖学金',
        'annual_year' => 2026,
        'level' => '校级',
    ]);

    $this->assertDatabaseHas('student_punishments', [
        'student_xgh' => '20260012',
        'student_name' => 'Punish Student',
        'reason' => '考试违纪',
        'punished_at' => '2026-05-01 00:00:00',
        'annual_year' => 2026,
    ]);
});

it('imports a standalone disposition workbook with native headers', function () {
    Student::query()->create([
        'xgh' => '2420500113',
        'xm' => '江赛男',
        'xbm' => '2',
        'rylx' => '0',
        'dwmc' => '金融与经贸学院',
        'dwbm' => 'JR',
    ]);

    $path = storage_path('app/test-native-punishments.xlsx');
    app(StudentImportWorkbook::class)->write($path, [
        'Sheet2' => [
            ['学年', '学号', '姓名', '性别', '年级', '二级学院', '班级', '时间', '事由', '处分', '文件号/发文时间', '', '期限', '解除时间', '是否解除'],
            ['2025-2026-1', '2420500113', '表内姓名', '女', '2024级', '金融与经贸学院', '24金融1', '2025-2026第二学期', '夹带小抄', '记过', '浙财大东〔2026〕50号', '46099', '10个月', '', '否'],
        ],
    ]);

    $file = fn () => new UploadedFile($path, '新增处分信息.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    $this->postJson('/student-imports/award_punishment', ['file' => $file()])
        ->assertOk()
        ->assertJsonPath('reward_imported', 0)
        ->assertJsonPath('punishment_imported', 1);
    $this->postJson('/student-imports/award_punishment', ['file' => $file()])
        ->assertOk()
        ->assertJsonPath('punishment_imported', 1);

    expect(StudentPunishment::query()->count())->toBe(1);
    $this->assertDatabaseHas('student_punishments', [
        'student_xgh' => '2420500113',
        'student_name' => '江赛男',
        'reason' => '记过：夹带小抄',
        'punished_at' => '2026-03-18 00:00:00',
        'annual_year' => 2026,
    ]);
});

it('reports an unrecognized award and punishment workbook', function () {
    $path = storage_path('app/test-unrecognized-punishments.xlsx');
    app(StudentImportWorkbook::class)->write($path, ['Sheet1' => [['学号', '姓名'], ['20260001', '张三']]]);

    $this->postJson('/student-imports/award_punishment', [
        'file' => new UploadedFile($path, 'unknown.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
    ])->assertUnprocessable()->assertJsonValidationErrors('file');
});

it('shows awards and punishments on the student profile', function () {
    Student::query()->create([
        'xgh' => '20260013',
        'xm' => 'Profile Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => 'Test College',
        'dwbm' => 'TEST',
    ]);

    StudentAward::query()->create([
        'student_xgh' => '20260013',
        'student_name' => 'Profile Student',
        'award_name' => '国家奖学金',
        'annual_year' => 2026,
        'level' => '国家级',
    ]);

    StudentPunishment::query()->create([
        'student_xgh' => '20260013',
        'student_name' => 'Profile Student',
        'reason' => '迟到通报',
        'punished_at' => '2026-03-02',
        'annual_year' => 2026,
    ]);

    $this->get('/students/profile/20260013')
        ->assertOk()
        ->assertSee('"award_name":"\u56fd\u5bb6\u5956\u5b66\u91d1"', false)
        ->assertSee('"reason":"\u8fdf\u5230\u901a\u62a5"', false);
});
