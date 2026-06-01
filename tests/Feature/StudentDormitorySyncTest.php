<?php

use App\Models\Student;
use App\Models\StudentDormitory;
use App\Models\Pass;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('syncs dormitory rows from both middata tables', function () {
	config()->set('database.connections.middata', array_merge(
		config('database.connections.sqlite'),
		['database' => ':memory:']
	));

	Schema::connection('middata')->create('t_ejxyybt_bzksxsssxx', function (Blueprint $table) {
		$table->string('xh');
		$table->string('xm')->nullable();
		$table->string('xy')->nullable();
		$table->string('zy')->nullable();
		$table->string('bj')->nullable();
		$table->string('nj')->nullable();
		$table->string('ssh')->nullable();
		$table->string('ch')->nullable();
		$table->string('xz')->nullable();
		$table->string('qslx')->nullable();
		$table->string('xb')->nullable();
	});

	Schema::connection('middata')->create('t_ejxyybt_bzkslsssxx', function (Blueprint $table) {
		$table->string('id')->nullable();
		$table->string('xm')->nullable();
		$table->string('xy')->nullable();
		$table->string('zy')->nullable();
		$table->string('bj')->nullable();
		$table->string('nj')->nullable();
		$table->string('ssh')->nullable();
		$table->string('ch')->nullable();
		$table->string('xz')->nullable();
		$table->string('qslx')->nullable();
		$table->string('xb')->nullable();
	});

	DB::connection('middata')->table('t_ejxyybt_bzksxsssxx')->insert([
		'xh' => '20260001',
		'xm' => '新生甲',
		'xy' => '会计学院',
		'zy' => '会计学',
		'bj' => '24会计1班',
		'nj' => '2024',
		'ssh' => 'A101',
		'ch' => '1',
		'xz' => '4',
		'qslx' => '四人间',
		'xb' => '男',
	]);

	DB::connection('middata')->table('t_ejxyybt_bzkslsssxx')->insert([
		'id' => '20250001',
		'xm' => '老生乙',
		'xy' => '金融学院',
		'zy' => '金融学',
		'bj' => '23金融2班',
		'nj' => '2023',
		'ssh' => 'B203',
		'ch' => '2',
		'xz' => '4',
		'qslx' => '四人间',
		'xb' => '女',
	]);

	$this->artisan('sync:student-dormitories-from-middata')->assertExitCode(0);

	$this->assertDatabaseHas('student_dormitories', [
		'xh' => '20260001',
		'ssh' => 'A101',
		'source_table' => 't_ejxyybt_bzksxsssxx',
	]);

	$this->assertDatabaseHas('student_dormitories', [
		'xh' => '20250001',
		'ssh' => 'B203',
		'source_table' => 't_ejxyybt_bzkslsssxx',
	]);
});

it('shows dormitory and roommates on student profile', function () {
	Carbon::setTestNow('2026-05-29 12:00:00');

	Student::query()->create([
		'xgh' => '20260011',
		'xm' => '张同学',
		'xbm' => '1',
		'rylx' => '0',
		'dwmc' => '测试学院',
		'dwbm' => 'T',
		'bjmc' => '24级1班',
		'last_smsj' => now()->subDay(),
	]);

	Student::query()->create([
		'xgh' => '20260012',
		'xm' => '李同学',
		'xbm' => '1',
		'rylx' => '0',
		'dwmc' => '测试学院',
		'dwbm' => 'T',
		'last_smsj' => now()->subDays(8),
	]);

	Student::query()->create([
		'xgh' => '20260013',
		'xm' => '王同学',
		'xbm' => '1',
		'rylx' => '0',
		'dwmc' => '测试学院',
		'dwbm' => 'T',
		'last_smsj' => now()->subDays(10),
	]);

	StudentDormitory::query()->create([
		'xh' => '20260011',
		'xm' => '张同学',
		'xy' => '测试学院',
		'zy' => '计算机',
		'bj' => '24级1班',
		'nj' => '2024',
		'ssh' => 'A501',
		'ch' => '1',
		'xz' => '4',
		'qslx' => '四人间',
		'xb' => '男',
	]);

	StudentDormitory::query()->create([
		'xh' => '20260012',
		'xm' => '李同学',
		'xy' => '测试学院',
		'zy' => '计算机',
		'bj' => '24级1班',
		'nj' => '2024',
		'ssh' => 'A501',
		'ch' => '2',
		'xz' => '4',
		'qslx' => '四人间',
		'xb' => '男',
	]);

	StudentDormitory::query()->create([
		'xh' => '20260013',
		'xm' => '王同学',
		'xy' => '测试学院',
		'zy' => '计算机',
		'bj' => '24级1班',
		'nj' => '2024',
		'ssh' => 'A501',
		'ch' => '3',
		'xz' => '4',
		'qslx' => '四人间',
		'xb' => '男',
	]);

	Pass::query()->create([
		'gh' => '20260012',
		'xm' => '李同学',
		'device' => 'gate-a',
		'smdd' => '宿舍门口',
		'smsj' => now()->subDays(8),
		'crlx' => 'in',
	]);

	$response = $this->get('/students/profile/20260011')->assertOk();

	$response->assertSee('"dormitory"', false);
	$response->assertSee('"ssh":"A501"', false);
	$response->assertSee('"dormitorySummary"', false);
	$response->assertSee('"roommate_total":2', false);
	$response->assertSee('"lost_roommate_count":2', false);
	$response->assertSee('"high_risk_roommate_count":2', false);
	$response->assertSee('"roommates"', false);
	$response->assertSee('"xh":"20260012"', false);
	$response->assertSee('"status":"lost"', false);
	$response->assertSee('"last_smsj":"2026-05-21T12:00:00.000000Z"', false);

	Carbon::setTestNow();
});

it('skips blank student numbers and keeps higher-priority source rows', function () {
	config()->set('database.connections.middata', array_merge(
		config('database.connections.sqlite'),
		['database' => ':memory:']
	));

	Schema::connection('middata')->create('t_ejxyybt_bzksxsssxx', function (Blueprint $table) {
		$table->string('xh')->nullable();
		$table->string('xm')->nullable();
		$table->string('ssh')->nullable();
		$table->string('ch')->nullable();
		$table->string('qslx')->nullable();
	});

	Schema::connection('middata')->create('t_ejxyybt_bzkslsssxx', function (Blueprint $table) {
		$table->string('id')->nullable();
		$table->string('xm')->nullable();
		$table->string('ssh')->nullable();
		$table->string('ch')->nullable();
		$table->string('qslx')->nullable();
	});

	DB::connection('middata')->table('t_ejxyybt_bzksxsssxx')->insert([
		['xh' => ' 20269999 ', 'xm' => '优先新生', 'ssh' => 'N101', 'ch' => '1', 'qslx' => '四人间'],
		['xh' => '   ', 'xm' => '空学号', 'ssh' => 'N102', 'ch' => '2', 'qslx' => '四人间'],
	]);

	DB::connection('middata')->table('t_ejxyybt_bzkslsssxx')->insert([
		['id' => '20269999', 'xm' => '老生重复', 'ssh' => 'L201', 'ch' => '3', 'qslx' => '六人间'],
		['id' => null, 'xm' => '老生空学号', 'ssh' => 'L202', 'ch' => '4', 'qslx' => '六人间'],
	]);

	$this->artisan('sync:student-dormitories-from-middata')->assertExitCode(0);

	$this->assertDatabaseHas('student_dormitories', [
		'xh' => '20269999',
		'xm' => '优先新生',
		'ssh' => 'N101',
		'source_table' => 't_ejxyybt_bzksxsssxx',
	]);

	$this->assertDatabaseMissing('student_dormitories', [
		'source_table' => 't_ejxyybt_bzkslsssxx',
		'ssh' => 'L201',
	]);

	$this->assertDatabaseMissing('student_dormitories', [
		'ssh' => 'N102',
	]);

	expect(StudentDormitory::query()->count())->toBe(1);
});

it('shows dormitory detail page with all residents', function () {
	Carbon::setTestNow('2026-05-29 12:00:00');

	Student::query()->create([
		'xgh' => '20261001',
		'xm' => '甲同学',
		'xbm' => '1',
		'rylx' => '0',
		'dwmc' => '测试学院',
		'dwbm' => 'T',
		'last_smsj' => now()->subDay(),
	]);

	Student::query()->create([
		'xgh' => '20261002',
		'xm' => '乙同学',
		'xbm' => '1',
		'rylx' => '0',
		'dwmc' => '测试学院',
		'dwbm' => 'T',
		'last_smsj' => now()->subDays(9),
	]);

	StudentDormitory::query()->create([
		'xh' => '20261001',
		'xm' => '甲同学',
		'xy' => '测试学院',
		'ssh' => 'B602',
		'ch' => '1',
		'qslx' => '四人间',
	]);

	StudentDormitory::query()->create([
		'xh' => '20261002',
		'xm' => '乙同学',
		'xy' => '测试学院',
		'ssh' => 'B602',
		'ch' => '2',
		'qslx' => '四人间',
	]);

	$response = $this->get('/students/dormitories/B602')->assertOk();

	$response->assertSee('data-page="studentDormitory"', false);
	$response->assertSee('"ssh":"B602"', false);
	$response->assertSee('"resident_total":2', false);
	$response->assertSee('"lost_roommate_count":1', false);
	$response->assertSee('"xh":"20261001"', false);
	$response->assertSee('"xh":"20261002"', false);

	Carbon::setTestNow();
});

