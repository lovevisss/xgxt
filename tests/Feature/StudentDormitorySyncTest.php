<?php

use App\Models\Pass;
use App\Models\Student;
use App\Models\StudentDormitory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('syncs dormitory bed rows from ssxxb type 7 records', function () {
	config()->set('database.connections.middata', array_merge(
		config('database.connections.sqlite'),
		['database' => ':memory:']
	));

	Schema::connection('middata')->create('t_ejxyybt_ssxxb', function (Blueprint $table) {
		$table->integer('sslx');
		$table->string('xgh')->nullable();
		$table->string('xm')->nullable();
		$table->string('ssmc')->nullable();
		$table->string('cwbq')->nullable();
		$table->string('bz')->nullable();
		$table->string('fjlx')->nullable();
		$table->string('xb')->nullable();
	});

	DB::connection('middata')->table('t_ejxyybt_ssxxb')->insert([
		[
			'sslx' => 7,
			'xgh' => '20260001',
			'xm' => 'Student A',
			'ssmc' => 'Building 1',
			'cwbq' => 'fallback-bed',
			'bz' => '1#410#4',
			'fjlx' => 'Quad',
			'xb' => 'M',
		],
		[
			'sslx' => 6,
			'xgh' => '20260002',
			'xm' => 'Wrong Type',
			'ssmc' => 'A102',
			'cwbq' => '2',
			'bz' => '1#410#5',
			'fjlx' => null,
			'xb' => null,
		],
	]);

	$this->artisan('sync:student-dormitories-from-middata')->assertExitCode(0);

	$this->assertDatabaseHas('student_dormitories', [
		'xh' => '20260001',
		'xm' => 'Student A',
		'ssh' => '1#410',
		'ch' => '4',
		'qslx' => 'Quad',
		'xb' => 'M',
		'source_table' => 't_ejxyybt_ssxxb',
	]);

	$this->assertDatabaseMissing('student_dormitories', [
		'xh' => '20260002',
	]);
});

it('removes old source rows and stale ssxxb rows during sync', function () {
	config()->set('database.connections.middata', array_merge(
		config('database.connections.sqlite'),
		['database' => ':memory:']
	));

	Schema::connection('middata')->create('t_ejxyybt_ssxxb', function (Blueprint $table) {
		$table->integer('sslx');
		$table->string('xgh')->nullable();
		$table->string('xm')->nullable();
		$table->string('ssmc')->nullable();
		$table->string('cwbq')->nullable();
		$table->string('bz')->nullable();
		$table->string('fjlx')->nullable();
	});

	StudentDormitory::query()->create([
		'xh' => '20269998',
		'xm' => 'Old Freshman Source',
		'ssh' => 'OLD101',
		'ch' => '1',
		'source_table' => 't_ejxyybt_bzksxsssxx',
	]);

	StudentDormitory::query()->create([
		'xh' => '20269997',
		'xm' => 'Old Returning Source',
		'ssh' => 'OLD201',
		'ch' => '2',
		'source_table' => 't_ejxyybt_bzkslsssxx',
	]);

	StudentDormitory::query()->create([
		'xh' => '20269996',
		'xm' => 'Stale New Source',
		'ssh' => 'STALE',
		'ch' => '3',
		'source_table' => 't_ejxyybt_ssxxb',
	]);

	StudentDormitory::query()->create([
		'xh' => '2520100103',
		'xm' => 'Guo Yuting',
		'ssh' => 'OLD301',
		'ch' => '4',
		'source_table' => null,
	]);

	DB::connection('middata')->table('t_ejxyybt_ssxxb')->insert([
		['sslx' => 7, 'xgh' => ' 20269999 ', 'xm' => 'Current Source', 'ssmc' => 'N101', 'cwbq' => '1', 'bz' => '1#410#1', 'fjlx' => 'Quad'],
		['sslx' => 7, 'xgh' => '   ', 'xm' => 'Blank Number', 'ssmc' => 'N102', 'cwbq' => '2', 'bz' => '1#410#2', 'fjlx' => 'Quad'],
		['sslx' => 7, 'xgh' => '20269999', 'xm' => 'Duplicate Number', 'ssmc' => 'N103', 'cwbq' => '3', 'bz' => '1#410#3', 'fjlx' => 'Six'],
	]);

	$this->artisan('sync:student-dormitories-from-middata')->assertExitCode(0);

	$this->assertDatabaseHas('student_dormitories', [
		'xh' => '20269999',
		'xm' => 'Current Source',
		'ssh' => '1#410',
		'ch' => '1',
		'source_table' => 't_ejxyybt_ssxxb',
	]);

	$this->assertDatabaseMissing('student_dormitories', [
		'source_table' => 't_ejxyybt_bzksxsssxx',
	]);

	$this->assertDatabaseMissing('student_dormitories', [
		'source_table' => 't_ejxyybt_bzkslsssxx',
	]);

	$this->assertDatabaseMissing('student_dormitories', [
		'xh' => '20269996',
	]);

	$this->assertDatabaseMissing('student_dormitories', [
		'xh' => '2520100103',
	]);

	$this->assertDatabaseMissing('student_dormitories', [
		'ssh' => 'N102',
	]);

	expect(StudentDormitory::query()->count())->toBe(1);
});

it('shows dormitory and roommates on student profile', function () {
	Carbon::setTestNow('2026-05-29 12:00:00');

	Student::query()->create([
		'xgh' => '20260011',
		'xm' => 'Student One',
		'xbm' => '1',
		'rylx' => '0',
		'dwmc' => 'Test College',
		'dwbm' => 'T',
		'bjmc' => 'Class 1',
		'last_smsj' => now()->subDay(),
	]);

	Student::query()->create([
		'xgh' => '20260012',
		'xm' => 'Student Two',
		'xbm' => '1',
		'rylx' => '0',
		'dwmc' => 'Test College',
		'dwbm' => 'T',
		'last_smsj' => now()->subDays(8),
	]);

	Student::query()->create([
		'xgh' => '20260013',
		'xm' => 'Student Three',
		'xbm' => '1',
		'rylx' => '0',
		'dwmc' => 'Test College',
		'dwbm' => 'T',
		'last_smsj' => now()->subDays(10),
	]);

	StudentDormitory::query()->create([
		'xh' => '20260011',
		'xm' => 'Student One',
		'xy' => 'Test College',
		'zy' => 'Computer Science',
		'bj' => 'Class 1',
		'nj' => '2024',
		'ssh' => 'A501',
		'ch' => '1',
		'xz' => '4',
		'qslx' => 'Quad',
		'xb' => 'M',
	]);

	StudentDormitory::query()->create([
		'xh' => '20260012',
		'xm' => 'Student Two',
		'xy' => 'Test College',
		'zy' => 'Computer Science',
		'bj' => 'Class 1',
		'nj' => '2024',
		'ssh' => 'A501',
		'ch' => '2',
		'xz' => '4',
		'qslx' => 'Quad',
		'xb' => 'M',
	]);

	StudentDormitory::query()->create([
		'xh' => '20260013',
		'xm' => 'Student Three',
		'xy' => 'Test College',
		'zy' => 'Computer Science',
		'bj' => 'Class 1',
		'nj' => '2024',
		'ssh' => 'A501',
		'ch' => '3',
		'xz' => '4',
		'qslx' => 'Quad',
		'xb' => 'M',
	]);

	StudentDormitory::query()->create([
		'xh' => '20260014',
		'xm' => 'Student Four',
		'xy' => 'Test College',
		'zy' => 'Computer Science',
		'bj' => 'Class 1',
		'nj' => '2024',
		'ssh' => 'A502',
		'ch' => '1',
		'xz' => '4',
		'qslx' => 'Quad',
		'xb' => 'M',
	]);

	Pass::query()->create([
		'gh' => '20260012',
		'xm' => 'Student Two',
		'device' => 'gate-a',
		'smdd' => 'Dorm Gate',
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
	$response->assertDontSee('"xh":"20260014"', false);
	$response->assertSee('"status":"lost"', false);
	$response->assertSee('"last_smsj":"2026-05-21T12:00:00.000000Z"', false);

	Carbon::setTestNow();
});

it('shows dormitory detail page with all residents', function () {
	Carbon::setTestNow('2026-05-29 12:00:00');

	Student::query()->create([
		'xgh' => '20261001',
		'xm' => 'Student A',
		'xbm' => '1',
		'rylx' => '0',
		'dwmc' => 'Test College',
		'dwbm' => 'T',
		'last_smsj' => now()->subDay(),
	]);

	Student::query()->create([
		'xgh' => '20261002',
		'xm' => 'Student B',
		'xbm' => '1',
		'rylx' => '0',
		'dwmc' => 'Test College',
		'dwbm' => 'T',
		'last_smsj' => now()->subDays(9),
	]);

	StudentDormitory::query()->create([
		'xh' => '20261001',
		'xm' => 'Student A',
		'xy' => 'Test College',
		'ssh' => 'B602',
		'ch' => '1',
		'qslx' => 'Quad',
	]);

	StudentDormitory::query()->create([
		'xh' => '20261002',
		'xm' => 'Student B',
		'xy' => 'Test College',
		'ssh' => 'B602',
		'ch' => '2',
		'qslx' => 'Quad',
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
