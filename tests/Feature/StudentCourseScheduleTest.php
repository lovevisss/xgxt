<?php

use App\Models\CourseSection;
use App\Models\Student;
use App\Models\StudentCourseSchedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('syncs course sections and student schedules from middata', function () {
    config()->set('database.connections.middata', array_merge(
        config('database.connections.sqlite'),
        ['database' => ':memory:']
    ));

    Schema::connection('middata')->create('t_ejxyybt_qxkcb', function (Blueprint $table) {
        $table->string('kkzt')->nullable();
        $table->string('kch')->nullable();
        $table->string('kcmc')->nullable();
        $table->string('xf')->nullable();
        $table->string('jxbmc')->nullable();
        $table->string('jxb_id');
        $table->string('kclb')->nullable();
        $table->string('kcxz')->nullable();
        $table->string('kcgs')->nullable();
        $table->string('kkxiaoq')->nullable();
        $table->string('ktrl')->nullable();
        $table->string('yxrs')->nullable();
        $table->string('zxs')->nullable();
        $table->string('jgh')->nullable();
        $table->string('rkjs')->nullable();
        $table->string('jszc')->nullable();
        $table->string('sksj')->nullable();
        $table->string('jxdd')->nullable();
        $table->string('lh')->nullable();
        $table->string('khfs')->nullable();
        $table->string('ksxs')->nullable();
        $table->string('kkxy')->nullable();
        $table->string('hbxx')->nullable();
        $table->string('xn')->nullable();
        $table->string('xq')->nullable();
        $table->string('qsjsz')->nullable();
    });

    Schema::connection('middata')->create('t_ejxyybt_bzkskbxx', function (Blueprint $table) {
        $table->string('pkbh');
        $table->string('xnxq');
        $table->string('xh');
        $table->string('kkyxbm')->nullable();
        $table->string('kkzybm')->nullable();
        $table->string('kkbjbm')->nullable();
        $table->string('kcbm')->nullable();
        $table->string('zc')->nullable();
        $table->string('qsz')->nullable();
        $table->string('zzz')->nullable();
        $table->string('dsz')->nullable();
        $table->string('xqj')->nullable();
        $table->string('jc')->nullable();
        $table->string('sksj')->nullable();
        $table->string('jxdd')->nullable();
        $table->string('jslxm')->nullable();
        $table->string('xf')->nullable();
        $table->string('llxs')->nullable();
        $table->string('syxs')->nullable();
        $table->string('sjxs')->nullable();
        $table->string('zxs')->nullable();
        $table->string('skjsgh')->nullable();
        $table->string('skjsxm')->nullable();
        $table->string('kcxzm')->nullable();
        $table->string('kcsxm')->nullable();
        $table->string('kslbm')->nullable();
        $table->string('ksfsm')->nullable();
        $table->string('ksxzm')->nullable();
        $table->string('tstamp')->nullable();
    });

    DB::connection('middata')->table('t_ejxyybt_qxkcb')->insert([
        'kkzt' => '开课',
        'kch' => '84701',
        'kcmc' => '概率论与数理统计',
        'xf' => '3.0',
        'jxbmc' => '(2026-2027-1)-84701-01',
        'jxb_id' => 'JXB001',
        'kclb' => '专业课',
        'kcxz' => '必修课',
        'kcgs' => '无',
        'kkxiaoq' => '长安校区',
        'ktrl' => '51',
        'yxrs' => '0',
        'zxs' => '48',
        'jgh' => '20230901',
        'rkjs' => '宋玉成',
        'jszc' => '无',
        'sksj' => '星期三第6-7节{1-16周}',
        'jxdd' => '3号教学楼301',
        'kkxy' => '信息与人工智能学院',
        'hbxx' => '25电商1;25电商2',
        'xn' => '2026-2027',
        'xq' => '1',
        'qsjsz' => '1-16周',
    ]);

    DB::connection('middata')->table('t_ejxyybt_bzkskbxx')->insert([
        'pkbh' => 'JXB001',
        'xnxq' => '2026-2027-1',
        'xh' => '20270001',
        'kkyxbm' => '100301',
        'kkbjbm' => '(2026-2027-1)-84701-01',
        'kcbm' => '84701',
        'qsz' => '1',
        'zzz' => '16',
        'xqj' => null,
        'jc' => '6-7',
        'sksj' => '星期三第6-7节{1-16周}',
        'jxdd' => '3号教学楼301',
        'xf' => '3.0',
        'zxs' => '48',
        'skjsgh' => '20230901',
        'skjsxm' => '宋玉成',
        'kcsxm' => '概率论与数理统计',
        'tstamp' => '20260530 120000',
    ]);

    $this->artisan('sync:course-schedules-from-middata')->assertExitCode(0);

    $this->assertDatabaseHas('course_sections', [
        'jxb_id' => 'JXB001',
        'kcmc' => '概率论与数理统计',
        'weekday' => 3,
        'period_start' => 6,
        'period_end' => 7,
        'week_start' => 1,
        'week_end' => 16,
    ]);

    $this->assertDatabaseHas('student_course_schedules', [
        'xnxq' => '2026-2027-1',
        'xh' => '20270001',
        'pkbh' => 'JXB001',
        'weekday' => 3,
        'period_start' => 6,
        'period_end' => 7,
        'week_start' => 1,
        'week_end' => 16,
    ]);
});

it('shows weekly course calendar on student profile', function () {
    Student::query()->create([
        'xgh' => '20270001',
        'xm' => '课程学生',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '测试学院',
        'dwbm' => 'T',
        'bjmc' => '25级1班',
    ]);

    CourseSection::query()->create([
        'jxb_id' => 'JXB001',
        'kkzt' => '开课',
        'kch' => '84701',
        'kcmc' => '概率论与数理统计',
        'xf' => '3.0',
        'jxbmc' => '(2026-2027-1)-84701-01',
        'kclb' => '专业课',
        'kcxz' => '必修课',
        'kkxiaoq' => '长安校区',
        'zxs' => '48',
        'rkjs' => '宋玉成',
        'sksj' => '星期三第6-7节{1-16周}',
        'jxdd' => '3号教学楼301',
        'kkxy' => '信息与人工智能学院',
        'xn' => '2026-2027',
        'xq' => '1',
        'qsjsz' => '1-16周',
        'weekday' => 3,
        'period_start' => 6,
        'period_end' => 7,
        'week_start' => 1,
        'week_end' => 16,
        'week_pattern' => null,
    ]);

    StudentCourseSchedule::query()->create([
        'xnxq' => '2026-2027-1',
        'xh' => '20270001',
        'pkbh' => 'JXB001',
        'kcbm' => '84701',
        'qsz' => 1,
        'zzz' => 16,
        'jc' => '6-7',
        'sksj' => '星期三第6-7节{1-16周}',
        'jxdd' => '3号教学楼301',
        'skjsxm' => '宋玉成',
        'kcsxm' => '概率论与数理统计',
        'zxs' => '48',
        'weekday_label' => '周三',
        'weekday' => 3,
        'period_start' => 6,
        'period_end' => 7,
        'week_start' => 1,
        'week_end' => 16,
        'week_pattern' => null,
    ]);

    $response = $this->get('/students/profile/20270001?xnxq=2026-2027-1&week=2')->assertOk();

    $response->assertSee('data-page="studentProfile"', false);
    $response->assertSee('"weeklySchedule"', false);
    $response->assertSee('"semesterLabel":"2026-2027 \\u5b66\\u5e74\\u7b2c1\\u5b66\\u671f"', false);
    $response->assertSee('"weekLabel":"\\u7b2c2\\u5468"', false);
    $response->assertSee('"course_name":"\\u6982\\u7387\\u8bba\\u4e0e\\u6570\\u7406\\u7edf\\u8ba1"', false);
    $response->assertSee('"teacher_name":"\\u5b8b\\u7389\\u6210"', false);
    $response->assertSee('"location":"3\\u53f7\\u6559\\u5b66\\u697c301"', false);
    $response->assertSee('"weekday_label":"\\u5468\\u4e09"', false);
    $response->assertSee('"prevWeekUrl"', false);
    $response->assertSee('"nextWeekUrl"', false);
});

it('filters weekly schedule by week range and odd or even pattern', function () {
    Student::query()->create([
        'xgh' => '20270002',
        'xm' => '周次过滤学生',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '测试学院',
        'dwbm' => 'T',
        'bjmc' => '25级2班',
    ]);

    CourseSection::query()->insert([
        [
            'jxb_id' => 'JXBODD',
            'kcmc' => '单周课程',
            'xn' => '2026-2027',
            'xq' => '1',
            'week_pattern' => 'odd',
        ],
        [
            'jxb_id' => 'JXBEVEN',
            'kcmc' => '双周课程',
            'xn' => '2026-2027',
            'xq' => '1',
            'week_pattern' => 'even',
        ],
    ]);

    StudentCourseSchedule::query()->insert([
        [
            'xnxq' => '2026-2027-1',
            'xh' => '20270002',
            'pkbh' => 'JXBODD',
            'kcsxm' => '单周课程',
            'weekday' => 1,
            'period_start' => 1,
            'period_end' => 2,
            'week_start' => 1,
            'week_end' => 16,
            'week_pattern' => 'odd',
        ],
        [
            'xnxq' => '2026-2027-1',
            'xh' => '20270002',
            'pkbh' => 'JXBEVEN',
            'kcsxm' => '双周课程',
            'weekday' => 2,
            'period_start' => 3,
            'period_end' => 4,
            'week_start' => 1,
            'week_end' => 16,
            'week_pattern' => 'even',
        ],
    ]);

    $week2 = $this->get('/students/profile/20270002?xnxq=2026-2027-1&week=2')->assertOk();
    $week2->assertSee('"course_name":"\u53cc\u5468\u8bfe\u7a0b"', false);
    $week2->assertDontSee('"course_name":"\u5355\u5468\u8bfe\u7a0b"', false);

    $week3 = $this->get('/students/profile/20270002?xnxq=2026-2027-1&week=3')->assertOk();
    $week3->assertSee('"course_name":"\u5355\u5468\u8bfe\u7a0b"', false);
    $week3->assertDontSee('"course_name":"\u53cc\u5468\u8bfe\u7a0b"', false);
});

