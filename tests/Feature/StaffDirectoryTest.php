<?php

use App\Auth\XgxtCasUserResolver;
use App\Models\CounselorManagementPermission;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('database.connections.middata', array_merge(
        config('database.connections.sqlite'),
        ['database' => ':memory:'],
    ));

    Schema::connection('middata')->create('t_bd_zzqxryxx', function (Blueprint $table) {
        $table->string('xgh');
        $table->string('xm');
        $table->string('dwbm')->nullable();
        $table->string('dwmc')->nullable();
        $table->string('dzyx')->nullable();
        $table->string('yddh')->nullable();
    });
});

function insertStaffSource(int $count = 10): void
{
    $rows = [];
    for ($index = 1; $index <= $count; $index++) {
        $number = str_pad((string) $index, 8, '0', STR_PAD_LEFT);
        $rows[] = [
            'xgh' => $number,
            'xm' => 'Teacher '.$index,
            'dwbm' => $index % 2 ? 'FIN' : 'ART',
            'dwmc' => $index % 2 ? 'Finance' : 'Art',
            'dzyx' => "teacher{$index}@example.test",
            'yddh' => '138'.str_pad((string) $index, 8, '0', STR_PAD_LEFT),
        ];
    }

    DB::connection('middata')->table('t_bd_zzqxryxx')->insert($rows);
}

it('syncs the minimum staff directory without creating or overwriting users', function () {
    insertStaffSource(4);
    $user = User::factory()->create(['cas_username' => '00000001', 'role' => User::ROLE_ADMIN]);

    $this->artisan('sync:staff-from-middata')
        ->assertExitCode(0)
        ->expectsOutputToContain('当前在职 4 人 / 2 个单位');

    expect(StaffMember::query()->count())->toBe(4);
    expect(User::query()->count())->toBe(1);
    expect($user->refresh()->role)->toBe(User::ROLE_ADMIN);
    $this->assertDatabaseHas('staff_members', [
        'employee_no' => '00000001',
        'name' => 'Teacher 1',
        'department_code' => 'FIN',
        'is_active' => true,
    ]);
});

it('deactivates missing staff permissions and does not restore them automatically', function () {
    insertStaffSource(4);
    User::factory()->create(['cas_username' => 'root', 'role' => User::ROLE_SUPER_ADMIN]);
    $departing = User::factory()->create(['cas_username' => '00000001', 'role' => User::ROLE_COUNSELOR]);
    CounselorManagementPermission::query()->create([
        'employee_no' => '00000001',
        'teacher_name' => 'Teacher 1',
        'all_colleges' => true,
        'department_codes' => [],
        'is_active' => true,
    ]);
    $this->artisan('sync:staff-from-middata')->assertExitCode(0);

    DB::connection('middata')->table('t_bd_zzqxryxx')->where('xgh', '00000001')->delete();
    $this->artisan('sync:staff-from-middata')->assertExitCode(0);

    expect(StaffMember::query()->where('employee_no', '00000001')->value('is_active'))->toBeFalsy();
    expect($departing->refresh()->role)->toBe(User::ROLE_STAFF);
    expect(CounselorManagementPermission::query()->where('employee_no', '00000001')->value('is_active'))->toBeFalsy();
    $this->assertDatabaseHas('counselor_management_logs', ['action' => 'staff.deactivated', 'subject' => '00000001']);

    DB::connection('middata')->table('t_bd_zzqxryxx')->insert([
        'xgh' => '00000001', 'xm' => 'Teacher 1', 'dwbm' => 'FIN', 'dwmc' => 'Finance', 'dzyx' => null, 'yddh' => null,
    ]);
    $this->artisan('sync:staff-from-middata')->assertExitCode(0);
    expect(StaffMember::query()->where('employee_no', '00000001')->value('is_active'))->toBeTruthy();
    expect($departing->refresh()->role)->toBe(User::ROLE_STAFF);
    expect(CounselorManagementPermission::query()->where('employee_no', '00000001')->value('is_active'))->toBeFalsy();
});

it('rejects empty sources and abnormal drops without deactivating staff', function () {
    $this->artisan('sync:staff-from-middata')->assertExitCode(1);
    expect(StaffMember::query()->count())->toBe(0);

    insertStaffSource(10);
    $this->artisan('sync:staff-from-middata')->assertExitCode(0);
    DB::connection('middata')->table('t_bd_zzqxryxx')->whereNotIn('xgh', ['00000001', '00000002'])->delete();

    $this->artisan('sync:staff-from-middata')->assertExitCode(1);
    expect(StaffMember::query()->where('is_active', true)->count())->toBe(10);
});

it('rejects a sync that would disable the final super administrator', function () {
    insertStaffSource(10);
    $this->artisan('sync:staff-from-middata')->assertExitCode(0);
    User::factory()->create(['cas_username' => '00000001', 'role' => User::ROLE_SUPER_ADMIN]);
    DB::connection('middata')->table('t_bd_zzqxryxx')->where('xgh', '00000001')->delete();

    $this->artisan('sync:staff-from-middata')->assertExitCode(1);
    expect(StaffMember::query()->where('employee_no', '00000001')->value('is_active'))->toBeTruthy();
    expect(User::query()->where('cas_username', '00000001')->value('role'))->toBe(User::ROLE_SUPER_ADMIN);
});

it('lists and filters staff and provisions permissions before first login', function () {
    config(['cas.enabled' => true]);
    insertStaffSource(4);
    $this->artisan('sync:staff-from-middata')->assertExitCode(0);
    Student::query()->create(['xgh' => 'student1', 'xm' => 'Student', 'xbm' => '1', 'rylx' => '0', 'dwbm' => 'FIN', 'dwmc' => 'Finance']);
    Student::query()->create(['xgh' => 'student2', 'xm' => 'Student', 'xbm' => '1', 'rylx' => '0', 'dwbm' => 'ART', 'dwmc' => 'Art']);
    $superAdmin = User::factory()->create(['cas_username' => 'root', 'role' => User::ROLE_SUPER_ADMIN]);
    $normalAdmin = User::factory()->create(['cas_username' => 'admin', 'role' => User::ROLE_ADMIN]);

    $this->withSession(['cas_user' => ['user' => 'root']])
        ->getJson('/admin/users/data?q=Teacher+1&unit=FIN&status=active')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.employee_no', '00000001')
        ->assertJsonPath('data.0.account_exists', false);

    $member = StaffMember::query()->where('employee_no', '00000001')->firstOrFail();
    $this->withSession(['cas_user' => ['user' => $superAdmin->cas_username]])
        ->putJson("/admin/staff/{$member->id}/permissions", [
            'role' => User::ROLE_ADMIN,
            'counselor_management' => true,
            'all_colleges' => false,
            'department_codes' => ['FIN', 'ART'],
        ])->assertOk()
        ->assertJsonPath('data.role', User::ROLE_ADMIN)
        ->assertJsonPath('data.account_exists', true);

    $this->assertDatabaseHas('users', ['cas_username' => '00000001', 'role' => User::ROLE_ADMIN]);
    $this->assertDatabaseHas('counselor_management_permissions', ['employee_no' => '00000001', 'is_active' => true]);
    $this->assertDatabaseHas('counselor_management_logs', ['action' => 'permission.bundle.save', 'subject' => '00000001']);

    $this->withSession(['cas_user' => ['user' => $normalAdmin->cas_username]])
        ->putJson("/admin/staff/{$member->id}/permissions", [
            'role' => User::ROLE_SUPER_ADMIN,
            'counselor_management' => false,
            'all_colleges' => false,
            'department_codes' => [],
        ])->assertForbidden();
});

it('uses directory data on CAS login and blocks inactive directory accounts', function () {
    StaffMember::query()->create([
        'employee_no' => 'teacher-active', 'name' => 'Directory Name', 'department_code' => 'FIN', 'department_name' => 'Finance',
        'email' => 'directory@example.test', 'is_active' => true,
    ]);
    $user = app(XgxtCasUserResolver::class)->resolve('teacher-active', ['name' => 'CAS Name']);
    expect($user->name)->toBe('Directory Name');
    expect($user->dwbm)->toBe('FIN');

    StaffMember::query()->create(['employee_no' => 'teacher-inactive', 'name' => 'Inactive', 'is_active' => false]);
    expect(fn () => app(XgxtCasUserResolver::class)->resolve('teacher-inactive', []))->toThrow(AccessDeniedHttpException::class);
});
