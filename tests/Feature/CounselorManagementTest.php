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
    return User::factory()->create(['role' => 'counselor', 'cas_username' => fake()->unique()->numerify('########'), 'dwbm' => $code, 'dwmc' => $code]);
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
    $this->putJson('/counselors/'.$fin->id, ['cas_username' => 'changed', 'name' => $fin->name, 'dwbm' => 'FIN'])->assertForbidden();
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
