<?php

use App\Models\CounselorClassAssignment;
use App\Models\Student;
use App\Models\StudentClass;
use App\Models\User;
use App\Services\StudentImportWorkbook;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('syncs student classes from read-only middata table', function () {
    config()->set('database.connections.middata', array_merge(
        config('database.connections.sqlite'),
        ['database' => ':memory:']
    ));

    Schema::connection('middata')->create('t_ejxyybt_bzksbjjbxx', function (Blueprint $table) {
        $table->string('bjbm');
        $table->string('bjmc')->nullable();
        $table->string('zybm')->nullable();
        $table->string('jbny')->nullable();
        $table->string('ssnj')->nullable();
        $table->string('bzxh')->nullable();
        $table->string('tstamp')->nullable();
    });

    DB::connection('middata')->table('t_ejxyybt_bzksbjjbxx')->insert([
        'bjbm' => '25203001',
        'bjmc' => '25经济1',
        'zybm' => '2030',
        'jbny' => '202509',
        'ssnj' => '2025',
        'bzxh' => '40',
        'tstamp' => '20260601 100000',
    ]);

    $this->artisan('sync:student-classes-from-middata')->assertExitCode(0);

    $this->assertDatabaseHas('student_classes', [
        'class_code' => '25203001',
        'class_name' => '25经济1',
        'major_code' => '2030',
        'grade' => '2025',
    ]);
});

it('imports counselors from excel and creates class assignments', function () {
    StudentClass::query()->create([
        'class_code' => '25203001',
        'class_name' => '25经济1',
        'grade' => '2025',
    ]);

    Student::query()->create([
        'xgh' => '20260041',
        'xm' => 'Class Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '金融与经贸学院',
        'dwbm' => 'FIN',
        'bjbm' => '25203001',
        'bjmc' => '25经济1',
    ]);

    $path = storage_path('app/test-counselors.xlsx');
    app(StudentImportWorkbook::class)->write($path, [
        '工作表1' => [
            ['辅导员信息', '', '', '', '', '', ''],
            ['工号', '姓名', '所属院系', '手机', '电话', '办公室', '带班情况'],
            ['20170901', '王琳', '金融与经贸学院', '17769617234', '87571172', '1J-511', '25经济1班、25金融C1'],
        ],
    ]);

    $this->artisan('import:counselor-assignments', ['path' => $path])->assertExitCode(0);

    $this->assertDatabaseHas('users', [
        'cas_username' => '20170901',
        'name' => '王琳',
        'role' => User::ROLE_COUNSELOR,
        'dwbm' => 'FIN',
        'phone' => '17769617234',
    ]);
    $this->assertDatabaseHas('counselor_class_assignments', [
        'class_code' => '25203001',
        'class_name' => '25经济1',
        'normalized_class_name' => '25经济1',
    ]);
});

it('limits counselor student visibility to assigned classes', function () {
    config()->set('cas.enabled', true);

    $counselor = User::factory()->create([
        'cas_username' => '20170901',
        'role' => User::ROLE_COUNSELOR,
        'dwbm' => 'FIN',
        'dwmc' => '金融与经贸学院',
    ]);

    CounselorClassAssignment::query()->create([
        'user_id' => $counselor->id,
        'class_code' => '25203001',
        'class_name' => '25经济1',
        'normalized_class_name' => '25经济1',
        'college_code' => 'FIN',
        'college_name' => '金融与经贸学院',
    ]);

    Student::query()->create([
        'xgh' => '20260051',
        'xm' => 'Visible Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '金融与经贸学院',
        'dwbm' => 'FIN',
        'bjbm' => '25203001',
        'bjmc' => '25经济1',
    ]);
    Student::query()->create([
        'xgh' => '20260052',
        'xm' => 'Hidden Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '金融与经贸学院',
        'dwbm' => 'FIN',
        'bjbm' => '25203002',
        'bjmc' => '25经济2',
    ]);

    $this->withSession([
        config('cas.session_key') => ['user' => '20170901'],
    ])->getJson('/students/data')
        ->assertOk()
        ->assertJsonPath('data.0.xgh', '20260051')
        ->assertJsonMissing(['xgh' => '20260052']);

    $this->withSession([
        config('cas.session_key') => ['user' => '20170901'],
    ])->get('/students/profile/20260052')
        ->assertForbidden();
});

it('lists recent four cohorts from the counselor college and supports fuzzy class search', function () {
    Carbon::setTestNow('2026-06-03 10:00:00');

    $counselor = User::factory()->create([
        'cas_username' => '20170902',
        'role' => User::ROLE_COUNSELOR,
        'dwbm' => 'FIN',
        'dwmc' => 'Finance College',
    ]);

    foreach ([
        ['20220001', '22FIN1', '22Finance1', 'FIN', 'Finance College'],
        ['20230001', '23FIN1', '23Finance1', 'FIN', 'Finance College'],
        ['20240001', '24FIN1', '24Finance1', 'FIN', 'Finance College'],
        ['20250001', '25FIN1', '25Finance1', 'FIN', 'Finance College'],
        ['20210001', '21FIN1', '21Finance1', 'FIN', 'Finance College'],
        ['20250002', '25ART1', '25Art1', 'ART', 'Art College'],
    ] as [$xgh, $classCode, $className, $collegeCode, $collegeName]) {
        Student::query()->create([
            'xgh' => $xgh,
            'xm' => $className.' Student',
            'xbm' => '1',
            'rylx' => '0',
            'dwmc' => $collegeName,
            'dwbm' => $collegeCode,
            'bjbm' => $classCode,
            'bjmc' => $className,
        ]);
    }

    $response = $this->getJson("/counselors/classes?counselor_id={$counselor->id}");

    $response->assertOk();
    $classNames = collect($response->json('data'))->pluck('class_name');
    expect($classNames->all())->toContain('22Finance1', '23Finance1', '24Finance1', '25Finance1');
    expect($classNames->all())->not->toContain('21Finance1', '25Art1');

    $searchResponse = $this->getJson("/counselors/classes?counselor_id={$counselor->id}&q=24Fin");

    $searchResponse->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.class_name', '24Finance1');
});
