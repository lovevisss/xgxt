<?php

use App\Models\{CounselorClassAssignment, Pass, StaffMember, Student, StudentAccessPermission, StudentDormitory, StudentFamily, User};
use App\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cas.enabled' => true]);
});

function workspaceStudent(string $id, string $college = 'FIN', array $extra = []): Student
{
    return Student::create(array_merge(['xgh' => $id, 'xm' => 'Student '.$id, 'xbm' => '1', 'rylx' => '0', 'dwbm' => $college, 'dwmc' => $college, 'bjbm' => $college === 'FIN' ? '2601' : '2501', 'bjmc' => $college.' class'], $extra));
}

function workspaceLogin($test, string $role = 'counselor', ?string $college = 'FIN'): User
{
    $user = User::factory()->create(['cas_username' => 'teacher', 'name' => 'Test Teacher', 'role' => $role, 'dwbm' => $college]);
    $test->withSession([config('cas.session_key') => ['user' => $user->cas_username]]);

    return $user;
}

it('scopes counselor lists filters counts graduates and records to their college without assignments', function () {
    workspaceLogin($this);
    workspaceStudent('1001');
    workspaceStudent('1002', 'ART');
    workspaceStudent('1003', 'FIN', ['student_category' => Student::CATEGORY_GRADUATED]);
    workspaceStudent('1004', 'ART', ['student_category' => Student::CATEGORY_GRADUATED]);
    $this->getJson('/students/data')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('summary.total', 1);
    $this->getJson('/students/filters')->assertOk()->assertJsonCount(1, 'grades')->assertJsonPath('grades.0.grade_code', '26');
    $this->getJson('/graduates/data')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.xgh', '1003');
    $this->get('/students/profile/1001')->assertOk();
    $this->get('/students/profile/1002')->assertForbidden();
    $this->getJson('/students/data/1002')->assertForbidden();
    $this->putJson('/students/data/1001', ['xm' => 'Changed'])->assertForbidden();
});

it('filters families and permits counselor writes only in their home college', function () {
    workspaceLogin($this);
    workspaceStudent('1001');
    workspaceStudent('1002', 'ART');
    $own = StudentFamily::create(['stu_no' => '1001', 'name' => 'Parent 1', 'sync_key' => 'p1']);
    $other = StudentFamily::create(['stu_no' => '1002', 'name' => 'Parent 2', 'sync_key' => 'p2']);
    $this->getJson('/student-families/data')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.can_update', true);
    $this->getJson('/student-families/data/'.$other->id)->assertForbidden();
    $this->putJson('/student-families/data/'.$own->id, ['name' => 'Updated'])->assertOk();
    $this->putJson('/student-families/data/'.$other->id, ['name' => 'Wrong'])->assertForbidden();
    $this->assertDatabaseHas('student_families', ['id' => $other->id, 'name' => 'Parent 2']);
});

it('adds and revokes personal scope without expanding family write permissions', function () {
    $user = workspaceLogin($this);
    workspaceStudent('1001'); workspaceStudent('1002', 'ART');
    $family = StudentFamily::create(['stu_no' => '1002', 'name' => 'Other', 'sync_key' => 'p2']);
    $grant = StudentAccessPermission::create(['employee_no' => $user->cas_username, 'scope_type' => 'college', 'department_code' => 'ART', 'is_active' => true]);
    $this->getJson('/students/data')->assertJsonCount(2, 'data');
    $this->getJson('/student-families/data')->assertJsonPath('data.0.can_update', false);
    $this->putJson('/student-families/data/'.$family->id, ['name' => 'Wrong'])->assertForbidden();
    $grant->update(['is_active' => false]);
    $this->getJson('/students/data')->assertJsonCount(1, 'data');
    $this->get('/students/profile/1002')->assertForbidden();
});

it('grants student affairs all students but no business or write capabilities', function () {
    workspaceLogin($this, User::ROLE_STUDENT_AFFAIRS);
    workspaceStudent('1001'); workspaceStudent('1002', 'ART');
    $family = StudentFamily::create(['stu_no' => '1001', 'name' => 'Parent', 'sync_key' => 'p1']);
    $this->getJson('/students/data')->assertOk()->assertJsonCount(2, 'data');
    $this->get('/students/profile/1002')->assertOk();
    $this->getJson('/student-families/data')->assertOk()->assertJsonPath('data.0.can_update', false);
    $this->putJson('/students/data/1001', ['xm' => 'Wrong'])->assertForbidden();
    $this->putJson('/student-families/data/'.$family->id, ['name' => 'Wrong'])->assertForbidden();
    foreach (['/student-imports', '/sync-tasks', '/student-access-permissions', '/admin/users', '/snippets'] as $url) $this->get($url)->assertForbidden();
    $this->postJson('/student-imports/loan', [])->assertForbidden();
    $this->postJson('/sync-tasks/data', [])->assertForbidden();
});

it('rejects missing college and cross college assignments as sources of default access', function () {
    $user = workspaceLogin($this, 'counselor', null);
    workspaceStudent('1001', 'ART');
    CounselorClassAssignment::create(['user_id' => $user->id, 'counselor_cas_username' => $user->cas_username, 'college_code' => 'ART', 'class_name' => 'ART class', 'normalized_class_name' => 'artclass', 'class_code' => '2501']);
    $this->getJson('/students/data')->assertForbidden();
    $this->get('/students/profile/1001')->assertForbidden();
    expect(app(WorkspaceContext::class)->build()['scopeLabel'])->toContain('未配置所属分院');
    $user->update(['dwbm' => 'FIN']);
    $this->getJson('/students/data')->assertOk()->assertJsonCount(0, 'data');
    $this->get('/students/profile/1001')->assertForbidden();
});

it('does not expose out of scope dormitory residents or companions', function () {
    workspaceLogin($this);
    workspaceStudent('1001'); workspaceStudent('1002', 'ART');
    foreach (['1001', '1002'] as $id) {
        StudentDormitory::create(['xh' => $id, 'xm' => 'Resident '.$id, 'ssh' => 'A101']);
        Pass::create(['gh' => $id, 'xm' => 'Pass '.$id, 'device' => 'device-'.$id, 'smdd' => 'Gate', 'smsj' => '2026-11-17 08:00:00', 'crlx' => 'in']);
    }
    $this->get('/students/dormitories/A101')->assertOk()->assertSee('"xh":"1001"', false)->assertDontSee('"xh":"1002"', false);
    $this->get('/students/profile/1001')->assertOk()->assertDontSee('"xgh":"1002"', false);
});

it('shares role aware navigation and applies role changes on the next request', function () {
    $user = workspaceLogin($this, User::ROLE_STUDENT_AFFAIRS);
    $context = app(WorkspaceContext::class)->build();
    expect($context['scopeLabel'])->toBe('全校学生');
    expect(array_column($context['navigation'], 'key'))->toBe(['home', 'students', 'families']);
    $this->get('/')->assertOk()->assertSee('data-workspace=', false)->assertDontSee('Voyager Campus');
    $user->update(['role' => 'super_admin']);
    $this->get('/admin/users')->assertOk();
    expect(app(WorkspaceContext::class)->build()['capabilities']['systemAdmin'])->toBeTrue();
});

it('blocks inactive accounts even when they have all scope or administrative roles', function () {
    workspaceLogin($this, 'admin');
    StaffMember::create(['employee_no' => 'teacher', 'name' => 'Inactive', 'is_active' => false]);
    $this->getJson('/students/data')->assertForbidden();
    $context = app(WorkspaceContext::class)->build();
    expect($context['groups'])->toBe([]);
    expect($context['status'])->toContain('停用');
});

it('allows only super admins to assign the student affairs role', function () {
    workspaceLogin($this, 'super_admin');
    $member = StaffMember::create(['employee_no' => 'new-teacher', 'name' => 'Future Teacher', 'is_active' => true]);
    $payload = ['role' => 'student_affairs', 'counselor_management' => false, 'all_colleges' => false, 'department_codes' => []];
    $this->putJson('/admin/staff/'.$member->id.'/permissions', $payload)->assertOk()->assertJsonPath('data.role', 'student_affairs');
    $this->assertDatabaseHas('users', ['cas_username' => 'new-teacher', 'role' => 'student_affairs']);
    User::where('cas_username', 'teacher')->update(['role' => 'admin']);
    $this->putJson('/admin/staff/'.$member->id.'/permissions', $payload)->assertForbidden();
});

it('renders guest and unprivileged workspaces without business entries', function () {
    $this->get('/')->assertOk();
    expect(app(WorkspaceContext::class)->build()['authenticated'])->toBeFalse();
    workspaceLogin($this, 'staff');
    expect(app(WorkspaceContext::class)->build()['groups'])->toBe([]);
    $this->get('/')->assertOk();
});

it('gives staff personal student scope read only access to family records', function () {
    workspaceLogin($this, 'staff');
    workspaceStudent('1001');
    $family = StudentFamily::create(['stu_no' => '1001', 'name' => 'Parent', 'sync_key' => 'staff-parent']);
    $grant = StudentAccessPermission::create(['employee_no' => 'teacher', 'scope_type' => 'all', 'is_active' => true]);
    $this->getJson('/student-families/data')->assertOk()->assertJsonPath('data.0.can_update', false);
    $this->getJson('/student-families/data/'.$family->id)->assertOk();
    $this->putJson('/student-families/data/'.$family->id, ['name' => 'Wrong'])->assertForbidden();
    $grant->delete();
    $this->getJson('/student-families/data')->assertForbidden();
});
