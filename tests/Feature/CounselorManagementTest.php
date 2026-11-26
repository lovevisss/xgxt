<?php

use App\Models\CounselorClassAssignment;
use App\Models\CounselorManagementPermission;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\StudentClass;
use App\Models\User;
use App\Services\CounselorClassCatalog;
use App\Services\CounselorWorkbookImport;
use App\Services\StudentImportWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-09-10');
    foreach (['FIN', 'ART'] as $code) {
        Student::create(['xgh' => $code.'26001', 'xm' => 'Student', 'xbm' => '1', 'rylx' => '0', 'dwbm' => $code, 'dwmc' => $code, 'bjbm' => $code.'26', 'bjmc' => '26Shared1']);
    }
});

function managementTeacher(string $code = 'FIN'): User
{
    $user = User::factory()->create(['role' => 'counselor', 'cas_username' => fake()->unique()->numerify('########'), 'dwbm' => $code, 'dwmc' => $code]);
    StaffMember::create(['employee_no' => $user->cas_username, 'name' => $user->name,
        'department_code' => $code, 'department_name' => $code, 'is_active' => true]);

    return $user;
}

function managementWorkbook(array $rows): string
{
    $path = storage_path('app/test-management-'.uniqid().'.xlsx');
    app(StudentImportWorkbook::class)->write($path, ['Sheet' => array_merge([['工号', '姓名', '所属院系', '手机', '电话', '办公室', '带班情况']], $rows)]);

    return $path;
}

it('shows empty new cohorts and keeps college names separate', function () {
    $teacher = managementTeacher();
    CounselorClassAssignment::create(['user_id' => $teacher->id, 'counselor_cas_username' => $teacher->cas_username, 'college_code' => 'FIN', 'college_name' => 'FIN', 'class_name' => '26New1', 'normalized_class_name' => '26new1']);
    StudentClass::create(['class_code' => 'NEW26', 'class_name' => '26New1', 'grade' => '2026']);
    StudentClass::create(['class_code' => 'OLD21', 'class_name' => '21Old1', 'grade' => '2021']);
    $data = $this->getJson('/counselors/classes?counselor_id='.$teacher->id)->assertOk();
    expect(collect($data->json('data'))->firstWhere('class_name', '26New1'))->toMatchArray(['class_code' => 'NEW26', 'student_count' => 0]);
    expect(collect($data->json('data'))->pluck('college_code')->unique()->all())->toBe(['FIN']);
    $this->getJson('/counselors/classes?grade=all&q=21Old')->assertOk()->assertJsonCount(1, 'data');
});

it('caches the class catalog until its source data changes', function () {
    $catalog = app(CounselorClassCatalog::class);
    $catalog->forget();
    expect($catalog->all()->pluck('class_name'))->not->toContain('26Fresh1');

    Student::create(['xgh' => 'FIN26002', 'xm' => 'Fresh', 'xbm' => '1', 'rylx' => '0', 'dwbm' => 'FIN', 'dwmc' => 'FIN', 'bjbm' => 'FIN2602', 'bjmc' => '26Fresh1']);
    expect($catalog->all()->pluck('class_name'))->not->toContain('26Fresh1');

    $catalog->forget();
    expect($catalog->all()->pluck('class_name'))->toContain('26Fresh1');
});

it('enforces scoped management without student access or delegation', function () {
    config(['cas.enabled' => true]);
    $manager = User::factory()->create(['role' => 'staff', 'cas_username' => 'manager']);
    $fin = managementTeacher();
    $art = managementTeacher('ART');
    $permission = CounselorManagementPermission::create(['employee_no' => 'manager', 'teacher_name' => 'Manager', 'all_colleges' => false, 'department_codes' => ['FIN'], 'is_active' => true]);
    $this->actingAs($manager)->withSession([config('cas.session_key') => ['user' => $manager->cas_username]]);
    $this->getJson('/counselors/data')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/counselors/'.$art->id)->assertForbidden();
    $this->postJson('/counselors/'.$art->id.'/classes', ['class_name' => '26Shared1'])->assertForbidden();
    $this->postJson('/counselors/'.$fin->id.'/classes', ['class_name' => '26Shared1', 'class_code' => 'ART26'])->assertUnprocessable();
    $this->postJson('/counselors/'.$fin->id.'/classes', ['class_name' => '26Shared1', 'class_code' => 'FIN26'])->assertCreated();
    $this->getJson('/students/data')->assertForbidden();
    $this->postJson('/counselors/permissions/data', [])->assertForbidden();
    $this->deleteJson('/counselors/'.$fin->id)->assertForbidden();
    $this->putJson('/counselors/'.$fin->id, ['cas_username' => 'changed', 'name' => $fin->name, 'dwbm' => 'FIN'])->assertUnprocessable();
    $this->putJson('/counselors/'.$fin->id, ['cas_username' => $fin->cas_username, 'name' => $fin->name, 'dwbm' => 'ART'])->assertForbidden();
    $permission->update(['department_codes' => ['FIN', 'ART']]);
    $this->getJson('/counselors/'.$art->id)->assertOk();
    $permission->update(['department_codes' => [], 'all_colleges' => true]);
    $this->getJson('/counselors/'.$art->id)->assertOk();
    $permission->update(['is_active' => false]);
    $this->getJson('/counselors/data')->assertForbidden();
});

it('replaces only imported teachers and preserves credentials and roles', function () {
    $teacher = managementTeacher();
    $outside = managementTeacher();
    $teacher->update(['role' => 'admin']);
    $password = $teacher->password;
    foreach ([$teacher, $outside] as $u) {
        CounselorClassAssignment::create(['user_id' => $u->id, 'counselor_cas_username' => $u->cas_username, 'college_code' => 'FIN', 'class_name' => '25Old1', 'normalized_class_name' => '25old1']);
    }
    $path = managementWorkbook([[$teacher->cas_username, $teacher->name, 'FIN', '', '', '', '26Shared1班、26NEW1']]);
    try {
        $preview = app(CounselorWorkbookImport::class)->preview($path, true);
        expect($preview['records'][0]['removed'])->toBe(['25old1']);
        expect($teacher->classAssignments()->count())->toBe(1);
        app(CounselorWorkbookImport::class)->commit($path, true);
        app(CounselorWorkbookImport::class)->commit($path, true);
        expect($teacher->refresh()->password)->toBe($password);
        expect($teacher->role)->toBe('admin');
        expect($teacher->classAssignments()->count())->toBe(2);
        expect($outside->classAssignments()->count())->toBe(1);
        $this->assertDatabaseHas('counselor_class_assignments', ['counselor_cas_username' => $teacher->cas_username, 'class_code' => 'FIN26']);
        $this->assertDatabaseHas('counselor_management_logs', ['action' => 'import', 'subject' => $teacher->cas_username]);
    } finally {
        unlink($path);
    }
    $path = managementWorkbook([[$teacher->cas_username, $teacher->name, 'FIN', '', '', '', '/']]);
    try {
        app(CounselorWorkbookImport::class)->commit($path, true);
        expect($teacher->classAssignments()->count())->toBe(0);
    } finally {
        unlink($path);
    }
});

it('rejects conflicting or out of scope imports without partial writes', function () {
    config(['cas.enabled' => true]);
    $manager = User::factory()->create(['role' => 'staff', 'cas_username' => 'manager']);
    CounselorManagementPermission::create(['employee_no' => 'manager', 'teacher_name' => 'Manager', 'all_colleges' => false, 'department_codes' => ['FIN'], 'is_active' => true]);
    $this->actingAs($manager)->withSession([config('cas.session_key') => ['user' => $manager->cas_username]]);
    $path = managementWorkbook([['12345', 'New', 'FIN', '', '', '', '26Shared1'], ['99999', 'Outside', 'ART', '', '', '', '/']]);
    try {
        $this->post('/counselors/import', ['file' => new UploadedFile($path, 'test.xlsx', null, null, true), 'commit' => '1'], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['cas_username' => '12345']);
    } finally {
        unlink($path);
    }
    $path = managementWorkbook([['12345', 'One', 'FIN', '', '', '', '/'], ['12345', 'Two', 'FIN', '', '', '', '/']]);
    try {
        expect(app(CounselorWorkbookImport::class)->preview($path, true)['errors'])->not->toBeEmpty();
    } finally {
        unlink($path);
    }
});

it('requires an active directory identity for creation and rejects mismatched import rows', function () {
    StaffMember::create(['employee_no' => 'active1', 'name' => 'Active Teacher', 'department_code' => 'FIN',
        'department_name' => 'FIN', 'is_active' => true]);
    StaffMember::create(['employee_no' => 'inactive1', 'name' => 'Former Teacher', 'department_code' => 'FIN',
        'department_name' => 'FIN', 'is_active' => false]);
    $this->postJson('/counselors/data', ['cas_username' => 'inactive1'])->assertUnprocessable();
    $this->postJson('/counselors/data', ['cas_username' => 'active1', 'dwbm' => 'ART'])->assertUnprocessable();
    $this->postJson('/counselors/data', ['cas_username' => 'active1'])->assertCreated()
        ->assertJsonPath('data.name', 'Active Teacher');
    $path = managementWorkbook([['inactive1', 'Former Teacher', 'FIN', '', '', '', '26Shared1'],
        ['active1', 'Active Teacher', 'ART', '', '', '', '26Shared1']]);
    try {
        $preview = app(CounselorWorkbookImport::class)->preview($path, true);
        expect($preview['errors'])->toHaveCount(2);
        $this->assertDatabaseCount('counselor_class_assignments', 0);
    } finally { unlink($path); }
});

it('does not grant access to same named classes in other colleges', function () {
    config(['cas.enabled' => true]);
    $teacher = managementTeacher();
    CounselorClassAssignment::create(['user_id' => $teacher->id, 'counselor_cas_username' => $teacher->cas_username, 'college_code' => 'FIN', 'class_name' => '26Shared1', 'normalized_class_name' => '26shared1']);
    $this->actingAs($teacher)->withSession([config('cas.session_key') => ['user' => $teacher->cas_username]])->getJson('/students/data')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.xgh', 'FIN26001');
    expect($teacher->canViewStudent(Student::where('xgh', 'ART26001')->first()))->toBeFalse();
    $this->get('/students/profile/ART26001')->assertForbidden();
});

it('lets super administrators grant permissions to active staff before their first login', function () {
    config(['cas.enabled' => true]);
    $admin = User::factory()->create(['role' => 'super_admin', 'cas_username' => 'admin']);
    StaffMember::query()->create(['employee_no' => 'future', 'name' => 'Future', 'is_active' => true]);
    $this->actingAs($admin)->withSession([config('cas.session_key') => ['user' => 'admin']])->postJson('/counselors/permissions/data', ['employee_no' => 'future', 'teacher_name' => 'Future', 'department_codes' => ['FIN', 'ART'], 'all_colleges' => false, 'is_active' => true])->assertOk();
    $permission = CounselorManagementPermission::first();
    $this->assertDatabaseHas('counselor_management_logs', ['action' => 'permission.save']);
    $this->deleteJson('/counselors/permissions/'.$permission->id)->assertOk();
    $this->assertDatabaseCount('counselor_management_permissions', 0);
});

it('links aliases within the college and preserves the match on reimport', function () {
    $teacher = managementTeacher();
    $assignment = CounselorClassAssignment::create(['user_id' => $teacher->id, 'counselor_cas_username' => $teacher->cas_username, 'college_code' => 'FIN', 'college_name' => 'FIN', 'class_name' => '26Alias', 'normalized_class_name' => '26alias']);
    $this->putJson('/counselors/'.$teacher->id.'/classes/'.$assignment->id.'/match', ['class_code' => 'ART26'])->assertUnprocessable();
    $this->putJson('/counselors/'.$teacher->id.'/classes/'.$assignment->id.'/match', ['class_code' => 'FIN26'])->assertOk();
    expect($teacher->canViewStudent(Student::where('xgh', 'FIN26001')->first()))->toBeTrue();
    $path = managementWorkbook([[$teacher->cas_username, $teacher->name, 'FIN', '', '', '', '26Alias']]);
    try {
        app(CounselorWorkbookImport::class)->commit($path, true);
        expect($assignment->refresh()->class_code)->toBe('FIN26');
    } finally {
        unlink($path);
    }
});

it('lists only current student classes without a matching counselor assignment', function () {
    $teacher = managementTeacher();
    CounselorClassAssignment::create(['user_id' => $teacher->id, 'counselor_cas_username' => $teacher->cas_username, 'college_code' => 'FIN', 'class_code' => 'FIN26', 'class_name' => '26Shared1', 'normalized_class_name' => '26shared1']);
    Student::create(['xgh' => 'FIN26002', 'xm' => 'Another', 'xbm' => '1', 'rylx' => '0', 'dwbm' => 'FIN', 'dwmc' => 'FIN', 'bjbm' => 'FIN2602', 'bjmc' => '26Shared1']);
    Student::create(['xgh' => 'FIN25001', 'xm' => 'Older', 'xbm' => '1', 'rylx' => '0', 'dwbm' => 'FIN', 'dwmc' => 'FIN', 'bjbm' => 'FIN25', 'bjmc' => '25Finance1']);
    Student::create(['xgh' => 'FIN24001', 'xm' => 'Graduated', 'xbm' => '1', 'rylx' => '0', 'dwbm' => 'FIN', 'dwmc' => 'FIN', 'bjbm' => 'FIN24', 'bjmc' => '24Finance1', 'student_category' => 'graduated']);
    Student::create(['xgh' => 'FIN23001', 'xm' => 'Staff', 'xbm' => '1', 'rylx' => '1', 'dwbm' => 'FIN', 'dwmc' => 'FIN', 'bjbm' => 'FIN23', 'bjmc' => '23Finance1']);
    Student::create(['xgh' => 'FIN26003', 'xm' => 'No code', 'xbm' => '1', 'rylx' => '0', 'dwbm' => 'FIN', 'dwmc' => 'FIN', 'bjbm' => null, 'bjmc' => '26NoCode1']);
    CounselorClassAssignment::create(['user_id' => $teacher->id, 'counselor_cas_username' => $teacher->cas_username, 'college_code' => 'FIN', 'class_code' => null, 'class_name' => '26NoCode1班', 'normalized_class_name' => '26nocode1']);

    $response = $this->getJson('/counselors/unassigned-classes')->assertOk()->assertJsonPath('total', 3);
    expect(collect($response->json('data'))->pluck('key'))->toContain('FIN|code:FIN2602', 'FIN|code:FIN25', 'ART|code:ART26');
    expect(collect($response->json('data'))->pluck('key'))->not->toContain('FIN|code:FIN26', 'FIN|name:26nocode1', 'FIN|code:FIN24');
    $this->getJson('/counselors/unassigned-classes?college=FIN&grade=2025&q=Finance')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.student_count', 1);
});

it('limits the unassigned list and binding to managed colleges', function () {
    config(['cas.enabled' => true]);
    $manager = User::factory()->create(['role' => 'staff', 'cas_username' => 'manager']);
    $fin = managementTeacher();
    $art = managementTeacher('ART');
    CounselorManagementPermission::create(['employee_no' => 'manager', 'teacher_name' => 'Manager', 'all_colleges' => false, 'department_codes' => ['FIN'], 'is_active' => true]);
    $this->actingAs($manager)->withSession([config('cas.session_key') => ['user' => 'manager']]);
    $this->getJson('/counselors/unassigned-classes')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.college_code', 'FIN');
    $this->getJson('/counselors/unassigned-classes?college=ART')->assertForbidden();
    $this->postJson('/counselors/unassigned-classes/bind', ['class_key' => 'ART|code:ART26', 'counselor_id' => $art->id])->assertForbidden();
    $this->postJson('/counselors/unassigned-classes/bind', ['class_key' => 'FIN|code:FIN26', 'counselor_id' => $art->id])->assertForbidden();
    $this->postJson('/counselors/unassigned-classes/bind', ['class_key' => 'FIN|code:FIN26', 'counselor_id' => $fin->id])->assertCreated();
    $this->getJson('/counselors/unassigned-classes')->assertOk()->assertJsonPath('total', 0);
    $this->actingAs($fin)->withSession([config('cas.session_key') => ['user' => $fin->cas_username]]);
    $this->getJson('/counselors/unassigned-classes')->assertForbidden();
    $this->postJson('/counselors/unassigned-classes/bind', ['class_key' => 'ART|code:ART26', 'counselor_id' => $fin->id])->assertForbidden();
});

it('binds an unassigned class once and reports stale submissions', function () {
    $teacher = managementTeacher();
    $other = managementTeacher();
    Student::create(['xgh' => 'FIN26002', 'xm' => 'Same name', 'xbm' => '1', 'rylx' => '0', 'dwbm' => 'FIN', 'dwmc' => 'FIN', 'bjbm' => 'FIN2602', 'bjmc' => '26Shared1']);
    $payload = ['class_key' => 'FIN|code:FIN26', 'counselor_id' => $teacher->id];
    $this->postJson('/counselors/unassigned-classes/bind', $payload)->assertCreated();
    $this->assertDatabaseHas('counselor_class_assignments', ['counselor_cas_username' => $teacher->cas_username, 'class_code' => 'FIN26']);
    $this->postJson('/counselors/unassigned-classes/bind', $payload)->assertStatus(409);
    $this->postJson('/counselors/unassigned-classes/bind', ['class_key' => 'FIN|code:FIN2602', 'counselor_id' => $teacher->id])->assertUnprocessable();
    $this->assertDatabaseHas('counselor_class_assignments', ['counselor_cas_username' => $teacher->cas_username, 'class_code' => 'FIN26']);
    $this->postJson('/counselors/unassigned-classes/bind', ['class_key' => 'ART|code:ART26', 'counselor_id' => $other->id])->assertUnprocessable();
    $this->postJson('/counselors/unassigned-classes/bind', ['class_key' => 'FIN|code:FIN2602', 'counselor_id' => $other->id])->assertCreated();
    $this->assertDatabaseCount('counselor_class_assignments', 2);
});

it('can bind a current class that has no class code', function () {
    $teacher = managementTeacher();
    Student::create(['xgh' => 'FIN26004', 'xm' => 'No code', 'xbm' => '1', 'rylx' => '0', 'dwbm' => 'FIN', 'dwmc' => 'FIN', 'bjbm' => null, 'bjmc' => '26MissingCode1']);
    $this->getJson('/counselors/unassigned-classes?q=MissingCode')->assertOk()
        ->assertJsonPath('data.0.key', 'FIN|name:26missingcode1')
        ->assertJsonPath('data.0.class_code', null);
    $this->postJson('/counselors/unassigned-classes/bind', ['class_key' => 'FIN|name:26missingcode1', 'counselor_id' => $teacher->id])->assertCreated();
    $this->assertDatabaseHas('counselor_class_assignments', ['counselor_cas_username' => $teacher->cas_username, 'class_code' => null, 'normalized_class_name' => '26missingcode1']);
    $this->getJson('/counselors/unassigned-classes?q=MissingCode')->assertOk()->assertJsonPath('total', 0);
});

it('requires an explicit transfer when another counselor owns a class', function () {
    $first = managementTeacher();
    $second = managementTeacher();
    $this->postJson('/counselors/'.$first->id.'/classes', ['class_name' => '26Shared1', 'class_code' => 'FIN26'])->assertCreated();
    $this->getJson('/counselors/classes?counselor_id='.$second->id.'&grade=all&q=26Shared1')->assertOk()->assertJsonPath('data.0.owner_names.0', $first->name);
    $ownerId = CounselorClassAssignment::where('counselor_cas_username', $first->cas_username)->value('id');
    $this->postJson('/counselors/'.$second->id.'/classes', ['class_name' => '26Shared1', 'class_code' => 'FIN26'])->assertStatus(409);
    $this->postJson('/counselors/'.$second->id.'/classes', ['class_name' => '26Shared1', 'class_code' => 'FIN26', 'transfer' => true, 'expected_owner_ids' => [999]])->assertStatus(409);
    $this->postJson('/counselors/'.$second->id.'/classes', ['class_name' => '26Shared1', 'class_code' => 'FIN26', 'transfer' => true, 'expected_owner_ids' => [$ownerId]])->assertCreated();
    $this->assertDatabaseMissing('counselor_class_assignments', ['id' => $ownerId]);
    $this->assertDatabaseHas('counselor_class_assignments', ['counselor_cas_username' => $second->cas_username, 'class_code' => 'FIN26']);
    $this->assertDatabaseCount('counselor_class_assignments', 1);
    $this->assertDatabaseHas('counselor_management_logs', ['action' => 'class.transfer', 'subject' => $second->cas_username]);
});

it('keeps multi-class additions atomic until transfers are confirmed', function () {
    $first = managementTeacher();
    $second = managementTeacher();
    Student::create(['xgh' => 'FIN26002', 'xm' => 'Other', 'xbm' => '1', 'rylx' => '0', 'dwbm' => 'FIN', 'dwmc' => 'FIN', 'bjbm' => 'FIN2602', 'bjmc' => '26Other1']);
    $this->postJson('/counselors/'.$first->id.'/classes', ['class_name' => '26Shared1', 'class_code' => 'FIN26'])->assertCreated();
    $ownerId = CounselorClassAssignment::first()->id;
    $classes = [['class_name' => '26Shared1', 'class_code' => 'FIN26'], ['class_name' => '26Other1', 'class_code' => 'FIN2602']];
    $response = $this->postJson('/counselors/'.$second->id.'/classes/bulk', ['classes' => $classes])->assertStatus(409);
    $this->assertDatabaseCount('counselor_class_assignments', 1);
    $key = $response->json('conflicts.0.key');
    $this->postJson('/counselors/'.$second->id.'/classes/bulk', ['classes' => $classes, 'transfer' => true, 'expected_owners' => [$key => [$ownerId]]])->assertCreated();
    $this->assertDatabaseCount('counselor_class_assignments', 2);
    $this->assertDatabaseMissing('counselor_class_assignments', ['id' => $ownerId]);
});

it('imports a new sole owner and rejects two owners in the same workbook', function () {
    $first = managementTeacher();
    $second = managementTeacher();
    foreach ([$first, $second] as $user) {
        CounselorClassAssignment::create(['user_id' => $user->id, 'counselor_cas_username' => $user->cas_username,
            'college_code' => 'FIN', 'college_name' => 'FIN', 'class_code' => 'FIN26', 'class_name' => '26Shared1', 'normalized_class_name' => '26shared1', 'source' => 'excel']);
    }
    $third = managementTeacher();
    $path = managementWorkbook([[$third->cas_username, $third->name, 'FIN', '', '', '', '26Shared1']]);
    try {
        $preview = app(CounselorWorkbookImport::class)->preview($path, true);
        expect($preview['records'][0]['transfers'][0])->toContain($first->name, $second->name);
        app(CounselorWorkbookImport::class)->commit($path, true);
        $this->assertDatabaseCount('counselor_class_assignments', 1);
        $this->assertDatabaseHas('counselor_class_assignments', ['counselor_cas_username' => $third->cas_username, 'class_code' => 'FIN26']);
    } finally { unlink($path); }
    $path = managementWorkbook([[$first->cas_username, $first->name, 'FIN', '', '', '', '26Shared1'], [$second->cas_username, $second->name, 'FIN', '', '', '', '26Shared1']]);
    try {
        expect(app(CounselorWorkbookImport::class)->preview($path, true)['errors'])->not->toBeEmpty();
        $this->post('/counselors/import', ['file' => new UploadedFile($path, 'test.xlsx', null, null, true), 'commit' => '1'], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseHas('counselor_class_assignments', ['counselor_cas_username' => $third->cas_username, 'class_code' => 'FIN26']);
    } finally { unlink($path); }
    $path = managementWorkbook([[$third->cas_username, $third->name, 'FIN', '', '', '', '/'], [$first->cas_username, $first->name, 'FIN', '', '', '', '26Shared1']]);
    try {
        app(CounselorWorkbookImport::class)->commit($path, true);
        $this->assertDatabaseCount('counselor_class_assignments', 1);
        $this->assertDatabaseHas('counselor_class_assignments', ['counselor_cas_username' => $first->cas_username, 'class_code' => 'FIN26']);
    } finally { unlink($path); }
});

it('lists duplicate owners and keeps only the chosen counselor', function () {
    $first = managementTeacher();
    $second = managementTeacher();
    foreach ([$first, $second] as $user) {
        CounselorClassAssignment::create(['user_id' => $user->id, 'counselor_cas_username' => $user->cas_username,
            'college_code' => 'FIN', 'college_name' => 'FIN', 'class_code' => 'FIN26', 'class_name' => '26Shared1', 'normalized_class_name' => '26shared1']);
    }
    $response = $this->getJson('/counselors/conflicts')->assertOk()->assertJsonCount(1, 'data');
    $ids = collect($response->json('data.0.assignments'))->pluck('id')->all();
    $this->postJson('/counselors/conflicts/resolve', ['keep_assignment_id' => $ids[0], 'assignment_ids' => [$ids[0], 999]])->assertStatus(409);
    $this->postJson('/counselors/conflicts/resolve', ['keep_assignment_id' => $ids[0], 'assignment_ids' => $ids])->assertOk();
    $this->assertDatabaseCount('counselor_class_assignments', 1);
    $this->assertDatabaseHas('counselor_class_assignments', ['id' => $ids[0]]);
    $this->getJson('/counselors/conflicts')->assertOk()->assertJsonCount(0, 'data');
});

it('scopes conflict resolution to the managers college', function () {
    config(['cas.enabled' => true]);
    $manager = User::factory()->create(['role' => 'staff', 'cas_username' => 'manager']);
    CounselorManagementPermission::create(['employee_no' => 'manager', 'teacher_name' => 'Manager', 'all_colleges' => false, 'department_codes' => ['FIN'], 'is_active' => true]);
    foreach (['FIN', 'ART'] as $college) {
        foreach ([managementTeacher($college), managementTeacher($college)] as $user) {
            CounselorClassAssignment::create(['user_id' => $user->id, 'counselor_cas_username' => $user->cas_username,
                'college_code' => $college, 'class_code' => $college.'26', 'class_name' => '26Shared1', 'normalized_class_name' => '26shared1']);
        }
    }
    $art = CounselorClassAssignment::where('college_code', 'ART')->pluck('id')->all();
    $this->actingAs($manager)->withSession([config('cas.session_key') => ['user' => 'manager']]);
    $this->getJson('/counselors/conflicts')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.college_code', 'FIN');
    $this->postJson('/counselors/conflicts/resolve', ['keep_assignment_id' => $art[0], 'assignment_ids' => $art])->assertForbidden();
});

it('checks ownership before matching an alias to a formal class', function () {
    $first = managementTeacher();
    $second = managementTeacher();
    $this->postJson('/counselors/'.$first->id.'/classes', ['class_name' => '26Shared1', 'class_code' => 'FIN26'])->assertCreated();
    $alias = CounselorClassAssignment::create(['user_id' => $second->id, 'counselor_cas_username' => $second->cas_username,
        'college_code' => 'FIN', 'class_name' => '26Alias', 'normalized_class_name' => '26alias']);
    $response = $this->putJson('/counselors/'.$second->id.'/classes/'.$alias->id.'/match', ['class_code' => 'FIN26'])->assertStatus(409);
    $ids = collect($response->json('conflicts.0.owners'))->pluck('id')->all();
    $this->putJson('/counselors/'.$second->id.'/classes/'.$alias->id.'/match', ['class_code' => 'FIN26', 'transfer' => true, 'expected_owner_ids' => $ids])->assertOk();
    $this->assertDatabaseCount('counselor_class_assignments', 1);
    $this->assertDatabaseHas('counselor_class_assignments', ['id' => $alias->id, 'class_code' => 'FIN26']);
});

it('keeps same named classes with different codes distinct and rejects ambiguous imports', function () {
    Student::create(['xgh' => 'FIN26002', 'xm' => 'Other', 'xbm' => '1', 'rylx' => '0', 'dwbm' => 'FIN', 'dwmc' => 'FIN', 'bjbm' => 'FIN2602', 'bjmc' => '26Shared1']);
    app(CounselorClassCatalog::class)->forget();
    $rows = app(CounselorClassCatalog::class)->all()->where('college_code', 'FIN')->where('class_name', '26Shared1');
    expect($rows->pluck('class_code')->all())->toContain('FIN26', 'FIN2602');
    $teacher = managementTeacher();
    $path = managementWorkbook([[$teacher->cas_username, $teacher->name, 'FIN', '', '', '', '26Shared1']]);
    try {
        expect(app(CounselorWorkbookImport::class)->preview($path, true)['errors'])->not->toBeEmpty();
    } finally { unlink($path); }
});
