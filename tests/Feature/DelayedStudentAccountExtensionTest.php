<?php

use App\Models\Student;
use App\Models\SyncAccountChange;
use App\Models\SyncTask;
use App\Models\User;
use App\Services\DelayedStudentAccountExtension;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['middata', 'status'] as $connection) {
        config()->set('database.connections.'.$connection, array_merge(
            config('database.connections.sqlite'),
            ['database' => ':memory:']
        ));
        DB::purge($connection);
    }

    Schema::connection('middata')->create('t_cx_zzqxryxx', function (Blueprint $table) {
        $table->string('xgh')->primary();
        $table->string('rylx');
    });
    Schema::connection('status')->create('tb_b_account', function (Blueprint $table) {
        $table->string('ID')->primary();
        $table->string('ACCOUNT_NAME')->unique();
        $table->date('ACCOUNT_EXPIRY_DATE')->nullable();
        $table->string('STATE');
        $table->integer('DELETED')->default(0);
        $table->integer('ACCOUNT_LOCKED')->nullable();
    });
    Carbon::setTestNow('2026-09-26 10:00:00');
});

afterEach(fn () => Carbon::setTestNow());

function delayedStudent(string $number, string $category = Student::CATEGORY_CURRENT): void
{
    Student::query()->create([
        'xgh' => $number,
        'xm' => '测试学生'.$number,
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '会计学院',
        'dwbm' => 'KJ',
        'bjmc' => '22会计1班',
        'student_category' => $category,
    ]);
}

function delayedAccount(string $number, ?string $expiry, string $state = 'NORMAL', array $extra = []): void
{
    DB::connection('status')->table('tb_b_account')->insert(array_merge([
        'ID' => 'account-'.$number,
        'ACCOUNT_NAME' => $number,
        'ACCOUNT_EXPIRY_DATE' => $expiry,
        'STATE' => $state,
        'DELETED' => 0,
        'ACCOUNT_LOCKED' => 0,
    ], $extra));
}

function delayedTask(): SyncTask
{
    return SyncTask::query()->create([
        'key' => 'delayed_student_accounts',
        'title' => '延毕生账号延期',
        'command' => 'sync:extend-delayed-student-accounts',
        'options' => [],
        'status' => SyncTask::STATUS_QUEUED,
    ]);
}

it('extends eligible accounts and records every actual person change without duplicating unchanged runs', function () {
    foreach (['2220110001', '2120110002', '2020110003', '2220110004', '2320110005'] as $number) {
        delayedStudent($number);
        DB::connection('middata')->table('t_cx_zzqxryxx')->insert(['xgh' => $number, 'rylx' => '0']);
    }
    delayedStudent('1920110006', Student::CATEGORY_GRADUATED);
    delayedStudent('2220110007');
    delayedAccount('2220110001', '2026-08-01');
    delayedAccount('2120110002', null, 'FREEZE');
    delayedAccount('2020110003', '2029-08-01');
    delayedAccount('2220110004', '2026-08-01', 'NORMAL', ['ACCOUNT_LOCKED' => 1]);
    delayedAccount('2320110005', '2026-08-01');
    delayedAccount('1920110006', '2026-08-01');
    delayedAccount('2220110007', '2026-08-01');

    $task = delayedTask();
    $this->artisan('sync:extend-delayed-student-accounts', ['--task-id' => $task->id])
        ->expectsOutputToContain('修改 2')
        ->assertExitCode(0);

    expect(DB::connection('status')->table('tb_b_account')->where('ACCOUNT_NAME', '2220110001')->value('ACCOUNT_EXPIRY_DATE'))->toBe('2027-09-26')
        ->and(DB::connection('status')->table('tb_b_account')->where('ACCOUNT_NAME', '2120110002')->value('STATE'))->toBe('NORMAL')
        ->and(DB::connection('status')->table('tb_b_account')->where('ACCOUNT_NAME', '2020110003')->value('ACCOUNT_EXPIRY_DATE'))->toBe('2029-08-01')
        ->and(DB::connection('status')->table('tb_b_account')->where('ACCOUNT_NAME', '2320110005')->value('ACCOUNT_EXPIRY_DATE'))->toBe('2026-08-01');

    $changes = SyncAccountChange::query()->where('sync_task_id', $task->id)->orderBy('student_xgh')->get();
    expect($changes)->toHaveCount(2)
        ->and($changes[0]->student_xgh)->toBe('2120110002')
        ->and($changes[0]->previous_expiry_date)->toBeNull()
        ->and($changes[0]->new_expiry_date)->toBe('2027-09-26')
        ->and($changes[0]->previous_state)->toBe('FREEZE')
        ->and($changes[0]->new_state)->toBe('NORMAL')
        ->and($changes[1]->student_name)->toContain('2220110001');

    $this->artisan('sync:extend-delayed-student-accounts', ['--task-id' => delayedTask()->id])
        ->expectsOutputToContain('修改 0')
        ->assertExitCode(0);
    expect(SyncAccountChange::query()->count())->toBe(2);
});

it('rolls back status changes and audit records when an account update fails', function () {
    foreach (['2220110001', '2220110002'] as $number) {
        delayedStudent($number);
        DB::connection('middata')->table('t_cx_zzqxryxx')->insert(['xgh' => $number, 'rylx' => '0']);
        delayedAccount($number, '2026-08-01');
    }
    DB::connection('status')->statement("CREATE TRIGGER fail_second_account BEFORE UPDATE ON tb_b_account WHEN NEW.ACCOUNT_NAME = '2220110002' BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");

    expect(fn () => app(DelayedStudentAccountExtension::class)->run(delayedTask()->id))->toThrow(Exception::class);
    expect(DB::connection('status')->table('tb_b_account')->where('ACCOUNT_NAME', '2220110001')->value('ACCOUNT_EXPIRY_DATE'))->toBe('2026-08-01')
        ->and(SyncAccountChange::query()->count())->toBe(0);
});

it('skips deleted locked and unsupported accounts and aborts before writing when middata fails', function () {
    foreach (range(1, 6) as $suffix) {
        $number = '22201100'.str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
        delayedStudent($number);
        if ($suffix <= 5) {
            DB::connection('middata')->table('t_cx_zzqxryxx')->insert(['xgh' => $number, 'rylx' => '0']);
        }
    }
    delayedAccount('2220110001', '2029-08-01', 'FREEZE');
    delayedAccount('2220110002', '2026-08-01', 'NORMAL', ['DELETED' => 1]);
    delayedAccount('2220110003', '2026-08-01', 'NORMAL', ['ACCOUNT_LOCKED' => 1]);
    delayedAccount('2220110004', '2026-08-01', 'DISABLED');
    // Student 5 has no account; student 6 is absent from middata.
    delayedAccount('2220110006', '2026-08-01');

    Schema::connection('middata')->drop('t_cx_zzqxryxx');
    expect(fn () => app(DelayedStudentAccountExtension::class)->run(delayedTask()->id))->toThrow(Exception::class);
    expect(SyncAccountChange::query()->count())->toBe(0)
        ->and(DB::connection('status')->table('tb_b_account')->where('ACCOUNT_NAME', '2220110001')->value('STATE'))->toBe('FREEZE');

    Schema::connection('middata')->create('t_cx_zzqxryxx', function (Blueprint $table) {
        $table->string('xgh')->primary();
        $table->string('rylx');
    });
    foreach (range(1, 5) as $suffix) {
        DB::connection('middata')->table('t_cx_zzqxryxx')->insert(['xgh' => '22201100'.str_pad((string) $suffix, 2, '0', STR_PAD_LEFT), 'rylx' => '0']);
    }

    $result = app(DelayedStudentAccountExtension::class)->run(delayedTask()->id);
    expect($result['updated'])->toBe(1)
        ->and($result['unfrozen'])->toBe(1)
        ->and($result['missing'])->toBe(1)
        ->and($result['skipped'])->toBe(4)
        ->and(DB::connection('status')->table('tb_b_account')->where('ACCOUNT_NAME', '2220110001')->value('ACCOUNT_EXPIRY_DATE'))->toBe('2029-08-01')
        ->and(DB::connection('status')->table('tb_b_account')->where('ACCOUNT_NAME', '2220110001')->value('STATE'))->toBe('NORMAL')
        ->and(DB::connection('status')->table('tb_b_account')->where('ACCOUNT_NAME', '2220110006')->value('ACCOUNT_EXPIRY_DATE'))->toBe('2026-08-01')
        ->and(SyncAccountChange::query()->count())->toBe(1);
});

it('rolls the cohort cutoff forward in September and restricts task details to super admins', function () {
    delayedStudent('2320110001');
    DB::connection('middata')->table('t_cx_zzqxryxx')->insert(['xgh' => '2320110001', 'rylx' => '0']);
    delayedAccount('2320110001', '2026-08-01');

    Carbon::setTestNow('2027-08-31');
    expect(app(DelayedStudentAccountExtension::class)->run(delayedTask()->id)['updated'])->toBe(0);
    Carbon::setTestNow('2027-09-01');
    $task = delayedTask();
    expect(app(DelayedStudentAccountExtension::class)->run($task->id)['updated'])->toBe(1);

    config()->set('cas.enabled', true);
    $admin = User::factory()->create(['cas_username' => 'admin-account', 'role' => User::ROLE_ADMIN]);
    $super = User::factory()->create(['cas_username' => 'super-account', 'role' => User::ROLE_SUPER_ADMIN]);
    $sessionKey = config('cas.session_key');

    $this->withSession([$sessionKey => ['user' => $admin->cas_username]])
        ->postJson('/sync-tasks/data', ['key' => 'delayed_student_accounts'])->assertForbidden();
    $this->withSession([$sessionKey => ['user' => $admin->cas_username]])
        ->getJson("/sync-tasks/data/{$task->id}/changes")->assertForbidden();
    $this->withSession([$sessionKey => ['user' => $admin->cas_username]])
        ->getJson("/sync-tasks/data/{$task->id}")->assertForbidden();
    $this->withSession([$sessionKey => ['user' => $admin->cas_username]])
        ->getJson('/sync-tasks/data')->assertJsonMissing(['key' => 'delayed_student_accounts']);

    $this->withSession([$sessionKey => ['user' => $admin->cas_username]])
        ->get('/sync-tasks')->assertDontSee('sync:extend-delayed-student-accounts', false);

    $this->withSession([$sessionKey => ['user' => $super->cas_username]])
        ->getJson("/sync-tasks/data/{$task->id}/changes")
        ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.student_xgh', '2320110001');
    $this->withSession([$sessionKey => ['user' => $super->cas_username]])
        ->postJson('/sync-tasks/data', ['key' => 'delayed_student_accounts'])->assertStatus(202);
    $this->withSession([$sessionKey => ['user' => $super->cas_username]])
        ->get('/sync-tasks')->assertSee('sync:extend-delayed-student-accounts', false);
});

it('paginates all person-level changes without the text log limit', function () {
    config()->set('cas.enabled', true);
    $super = User::factory()->create(['cas_username' => 'super-log', 'role' => User::ROLE_SUPER_ADMIN]);
    $task = delayedTask();

    foreach (range(1, 51) as $number) {
        SyncAccountChange::query()->create([
            'sync_task_id' => $task->id,
            'student_xgh' => '222011'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'student_name' => '测试学生'.$number,
            'college_name' => '会计学院',
            'previous_expiry_date' => null,
            'new_expiry_date' => '2027-09-26',
            'previous_state' => 'FREEZE',
            'new_state' => 'NORMAL',
            'changed_at' => now(),
        ]);
    }

    $session = [config('cas.session_key') => ['user' => $super->cas_username]];
    $this->withSession($session)->getJson("/sync-tasks/data/{$task->id}/changes?page=1")
        ->assertOk()->assertJsonPath('total', 51)->assertJsonCount(50, 'data');
    $this->withSession($session)->getJson("/sync-tasks/data/{$task->id}/changes?page=2")
        ->assertOk()->assertJsonPath('total', 51)->assertJsonCount(1, 'data');
});
