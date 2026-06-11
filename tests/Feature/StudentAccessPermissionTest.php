<?php

use App\Models\Student;
use App\Models\StudentAccessPermission;
use App\Models\User;
use App\Services\StudentImportWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function accessStudent(array $overrides): Student
{
    return Student::query()->create(array_merge([
        'xgh' => '20260001',
        'xm' => 'Access Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '金融与经贸学院',
        'dwbm' => '100301',
        'bjbm' => '25201001',
        'bjmc' => '25金融1',
    ], $overrides));
}

it('imports student access permissions from excel and upserts by employee number', function () {
    config()->set('cas.enabled', false);

    $path = storage_path('app/test-student-access-permissions.xlsx');
    app(StudentImportWorkbook::class)->write($path, [
        'Sheet1' => [
            ['', '', '', '', '', ''],
            ['序号', '单位', '工号', '姓名', '权限', '分院代码'],
            ['1', '金融与经贸学院', '20060017', '郭小蕾', '金融与经贸学院', '100301'],
            ['2', '学工', '20250065', '胡叶帅', '最高', '1003'],
            ['3', '', '', '', '', ''],
        ],
    ]);

    $this->postJson('/student-access-permissions/import', [
        'file' => new Illuminate\Http\UploadedFile($path, 'permissions.xlsx', null, null, true),
    ])->assertOk()
        ->assertJsonPath('created', 2)
        ->assertJsonPath('skipped', 1);

    expect(StudentAccessPermission::query()->where('employee_no', '20250065')->value('scope_type'))
        ->toBe(StudentAccessPermission::SCOPE_ALL);

    app(StudentImportWorkbook::class)->write($path, [
        'Sheet1' => [
            ['', '', '', '', '', ''],
            ['序号', '单位', '工号', '姓名', '权限', '分院代码'],
            ['1', '金融与经贸学院', '20060017', '新姓名', '金融与经贸学院', '100301'],
        ],
    ]);

    $this->postJson('/student-access-permissions/import', [
        'file' => new Illuminate\Http\UploadedFile($path, 'permissions.xlsx', null, null, true),
    ])->assertOk()
        ->assertJsonPath('updated', 1);

    $this->assertDatabaseHas('student_access_permissions', [
        'employee_no' => '20060017',
        'teacher_name' => '新姓名',
        'scope_type' => StudentAccessPermission::SCOPE_COLLEGE,
    ]);
});

it('allows student access permission users to view only authorized department students', function () {
    config()->set('cas.enabled', true);

    User::factory()->create([
        'cas_username' => '20060017',
        'role' => User::ROLE_STAFF,
    ]);
    StudentAccessPermission::query()->create([
        'employee_no' => '20060017',
        'teacher_name' => '郭小蕾',
        'scope_name' => '金融与经贸学院',
        'department_code' => '100301',
        'scope_type' => StudentAccessPermission::SCOPE_COLLEGE,
        'is_active' => true,
    ]);

    accessStudent(['xgh' => '20260001', 'dwbm' => '100301', 'dwmc' => '金融与经贸学院']);
    accessStudent(['xgh' => '20260002', 'dwbm' => '100302', 'dwmc' => '财税学院']);

    $session = [config('cas.session_key') => ['user' => '20060017']];

    $this->withSession($session)->get('/students')->assertOk();
    $this->withSession($session)->getJson('/students/data')
        ->assertOk()
        ->assertJsonFragment(['xgh' => '20260001'])
        ->assertJsonMissing(['xgh' => '20260002']);
    $this->withSession($session)->get('/students/profile/20260001')->assertOk();
    $this->withSession($session)->get('/students/profile/20260002')->assertForbidden();
    $this->withSession($session)->putJson('/students/data/20260001', ['xm' => 'Changed'])->assertForbidden();
});

it('allows all-scope access permission users to view all students', function () {
    config()->set('cas.enabled', true);

    User::factory()->create([
        'cas_username' => '20250065',
        'role' => User::ROLE_STAFF,
    ]);
    StudentAccessPermission::query()->create([
        'employee_no' => '20250065',
        'teacher_name' => '胡叶帅',
        'scope_name' => '最高',
        'department_code' => '1003',
        'scope_type' => StudentAccessPermission::SCOPE_ALL,
        'is_active' => true,
    ]);

    accessStudent(['xgh' => '20260001', 'dwbm' => '100301']);
    accessStudent(['xgh' => '20260002', 'dwbm' => '100302']);

    $this->withSession([config('cas.session_key') => ['user' => '20250065']])
        ->getJson('/students/data')
        ->assertOk()
        ->assertJsonFragment(['xgh' => '20260001'])
        ->assertJsonFragment(['xgh' => '20260002']);
});

it('prevents unprivileged users from student pages and access permission management', function () {
    config()->set('cas.enabled', true);

    User::factory()->create([
        'cas_username' => 'plain',
        'role' => User::ROLE_STAFF,
    ]);

    $session = [config('cas.session_key') => ['user' => 'plain']];

    $this->withSession($session)->get('/students')->assertForbidden();
    $this->withSession($session)->get('/student-access-permissions')->assertForbidden();
});

it('allows admins to manage student access permissions', function () {
    config()->set('cas.enabled', true);

    User::factory()->create([
        'cas_username' => 'admin',
        'role' => User::ROLE_ADMIN,
    ]);

    $session = [config('cas.session_key') => ['user' => 'admin']];

    $response = $this->withSession($session)->postJson('/student-access-permissions/data', [
        'employee_no' => '20060017',
        'teacher_name' => '郭小蕾',
        'unit_name' => '金融与经贸学院',
        'scope_name' => '金融与经贸学院',
        'department_code' => '100301',
        'is_active' => true,
    ]);

    $response->assertCreated()->assertJsonPath('data.scope_type', StudentAccessPermission::SCOPE_COLLEGE);
    $id = $response->json('data.id');

    $this->withSession($session)->putJson("/student-access-permissions/{$id}", [
        'employee_no' => '20060017',
        'teacher_name' => '郭小蕾',
        'unit_name' => '学工',
        'scope_name' => '最高',
        'department_code' => '1003',
        'is_active' => true,
    ])->assertOk()
        ->assertJsonPath('data.scope_type', StudentAccessPermission::SCOPE_ALL);

    $this->withSession($session)->deleteJson("/student-access-permissions/{$id}")->assertOk();
    $this->assertDatabaseMissing('student_access_permissions', ['id' => $id]);
});
