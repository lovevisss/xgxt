<?php

use App\Models\CounselorClassAssignment;
use App\Models\CounselorCollegeDelegation;
use App\Models\CounselorManagementPermission;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\StudentFamily;
use App\Models\User;
use App\Services\CounselorClassCatalog;
use App\Services\StudentAccess;
use App\Services\CounselorWorkbookImport;
use App\Services\StudentImportWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function delegatedTeacher(): User
{
    $teacher = User::factory()->create(['cas_username' => 'delegate', 'name' => '代班老师', 'role' => User::ROLE_COUNSELOR,
        'dwbm' => 'HOME', 'dwmc' => '学工部']);
    StaffMember::create(['employee_no' => 'delegate', 'name' => '代班老师', 'department_code' => 'HOME',
        'department_name' => '学工部', 'is_active' => true]);
    foreach ([['LAW1', '26社工1', 'LAW'], ['LAW2', '26社工2', 'LAW'], ['LAW3', '26社工3', 'LAW'],
        ['ART1', '26美术1', 'ART']] as [$code, $name, $college]) {
        Student::create(['xgh' => $code, 'xm' => $name, 'xbm' => '1', 'rylx' => '0',
            'dwbm' => $college, 'dwmc' => $college, 'bjbm' => $code, 'bjmc' => $name]);
    }
    app(CounselorClassCatalog::class)->forget();

    return $teacher;
}

it('grants a delegated college, binds only its formal classes, and limits student access to owned classes', function () {
    $teacher = delegatedTeacher();
    $this->postJson('/counselors/delegations', ['employee_no' => 'delegate', 'college_code' => 'LAW', 'reason' => '临时代班'])
        ->assertCreated();
    $this->postJson('/counselors/'.$teacher->id.'/classes', ['class_name' => '26社工1', 'class_code' => 'LAW1', 'college_code' => 'LAW'])->assertCreated();
    $this->postJson('/counselors/'.$teacher->id.'/classes', ['class_name' => '26美术1', 'class_code' => 'ART1', 'college_code' => 'ART'])->assertUnprocessable();

    config(['cas.enabled' => true]);
    expect(app(StudentAccess::class)->canViewStudent(Student::where('bjbm', 'LAW1')->first(), $teacher))->toBeTrue();
    expect(app(StudentAccess::class)->canViewStudent(Student::where('bjbm', 'LAW2')->first(), $teacher))->toBeFalse();
    expect(app(StudentAccess::class)->canEditFamilyStudent(Student::where('bjbm', 'LAW1')->first(), $teacher))->toBeTrue();
    expect(app(StudentAccess::class)->canEditFamilyStudent(Student::where('bjbm', 'LAW2')->first(), $teacher))->toBeFalse();
    $this->actingAs($teacher)->withSession([config('cas.session_key') => ['user' => 'delegate']]);
    $this->getJson('/students/data')->assertOk()->assertJsonCount(1, 'data');
});

it('lets a law manager maintain delegated law classes but not the teacher profile or other colleges', function () {
    $teacher = delegatedTeacher();
    CounselorCollegeDelegation::create(['employee_no' => 'delegate', 'college_code' => 'LAW', 'college_name' => 'LAW', 'is_active' => true]);
    config(['cas.enabled' => true]);
    $manager = User::factory()->create(['cas_username' => 'manager', 'role' => User::ROLE_STAFF]);
    CounselorManagementPermission::create(['employee_no' => 'manager', 'teacher_name' => 'Manager', 'department_codes' => ['LAW'],
        'all_colleges' => false, 'is_active' => true]);
    $this->actingAs($manager)->withSession([config('cas.session_key') => ['user' => 'manager']]);
    $this->getJson('/counselors/'.$teacher->id)->assertOk()->assertJsonPath('data.can_manage_profile', false);
    $this->postJson('/counselors/'.$teacher->id.'/classes', ['class_name' => '26社工1', 'class_code' => 'LAW1', 'college_code' => 'LAW'])->assertCreated();
    $this->putJson('/counselors/'.$teacher->id, ['cas_username' => 'delegate', 'phone' => '123'])->assertForbidden();
    $this->getJson('/counselors/delegations')->assertForbidden();
});

it('requires delegated assignments to be transferred before ending the delegation', function () {
    $teacher = delegatedTeacher();
    $delegation = CounselorCollegeDelegation::create(['employee_no' => 'delegate', 'college_code' => 'LAW', 'college_name' => 'LAW', 'is_active' => true]);
    $assignment = CounselorClassAssignment::create(['user_id' => $teacher->id, 'counselor_cas_username' => 'delegate',
        'college_code' => 'LAW', 'class_code' => 'LAW1', 'class_name' => '26社工1', 'normalized_class_name' => '26社工1']);
    $this->deleteJson('/counselors/delegations/'.$delegation->id)->assertStatus(409);
    $assignment->delete();
    $this->deleteJson('/counselors/delegations/'.$delegation->id)->assertOk();
    expect($delegation->fresh()->is_active)->toBeFalse();
});

it('imports an authorized delegated college without replacing classes in the home department', function () {
    $teacher = delegatedTeacher();
    Student::create(['xgh' => 'HOME1', 'xm' => 'Home student', 'xbm' => '1', 'rylx' => '0',
        'dwbm' => 'HOME', 'dwmc' => '学工部', 'bjbm' => 'HOME1', 'bjmc' => '26Home1']);
    app(CounselorClassCatalog::class)->forget();
    CounselorCollegeDelegation::create(['employee_no' => 'delegate', 'college_code' => 'LAW', 'college_name' => 'LAW', 'is_active' => true]);
    CounselorClassAssignment::create(['user_id' => $teacher->id, 'counselor_cas_username' => 'delegate',
        'college_code' => 'HOME', 'class_code' => 'HOME1', 'class_name' => '26Home1', 'normalized_class_name' => '26home1']);
    $path = storage_path('app/test-delegated-'.uniqid().'.xlsx');
    app(StudentImportWorkbook::class)->write($path, ['Sheet' => [
        ['工号', '姓名', '带班学院', '手机', '电话', '办公室', '带班情况'],
        ['delegate', '代班老师', 'LAW', '', '', '', '26社工1'],
    ]]);
    try {
        $preview = app(CounselorWorkbookImport::class)->preview($path, true);
        expect($preview['errors'])->toBe([]);
        app(CounselorWorkbookImport::class)->commit($path, true);
        expect($teacher->classAssignments()->pluck('college_code')->sort()->values()->all())->toBe(['HOME', 'LAW']);
    } finally { unlink($path); }
});

it('restores the four confirmed social-work classes only when their owners match the preview', function () {
    $wu = User::factory()->create(['cas_username' => '20250903', 'name' => '吴晨程', 'role' => User::ROLE_COUNSELOR,
        'dwbm' => '100405', 'dwmc' => '学工部']);
    StaffMember::create(['employee_no' => '20250903', 'name' => '吴晨程', 'department_code' => '100405',
        'department_name' => '学工部', 'is_active' => true]);
    $li = User::factory()->create(['cas_username' => '20250205', 'name' => '李梦妮', 'role' => User::ROLE_COUNSELOR,
        'dwbm' => '100306', 'dwmc' => '法律学院']);
    foreach ([['24206201', '24社工1'], ['24206202', '24社工2'], ['26206201', '26社工1'], ['26206202', '26社工2']] as [$code, $name]) {
        Student::create(['xgh' => $code, 'xm' => $name, 'xbm' => '1', 'rylx' => '0', 'dwbm' => '100306',
            'dwmc' => '法律与社会工作学院', 'bjbm' => $code, 'bjmc' => $name]);
        if (str_starts_with($code, '24')) CounselorClassAssignment::create(['user_id' => $li->id,
            'counselor_cas_username' => '20250205', 'college_code' => '100306', 'college_name' => '法律与社会工作学院',
            'class_code' => $code, 'class_name' => $name, 'normalized_class_name' => $name]);
    }
    app(CounselorClassCatalog::class)->forget();
    $this->artisan('counselors:restore-wu-delegation', ['--preview' => true])->assertExitCode(0);
    expect($wu->classAssignments()->count())->toBe(0);
    $this->artisan('counselors:restore-wu-delegation')->assertExitCode(0);
    $this->artisan('counselors:restore-wu-delegation')->assertExitCode(0);
    expect($wu->classAssignments()->count())->toBe(4);
    expect($li->classAssignments()->count())->toBe(0);
    $this->assertDatabaseCount('counselor_class_assignments', 4);
});

it('keeps same-named home and delegated classes distinct', function () {
    $teacher = delegatedTeacher();
    CounselorCollegeDelegation::create(['employee_no' => 'delegate', 'college_code' => 'LAW', 'college_name' => 'LAW', 'is_active' => true]);
    foreach ([['HOME', 'HOME_SHARED'], ['LAW', 'LAW_SHARED']] as [$college, $code]) {
        Student::create(['xgh' => $code, 'xm' => 'Student', 'xbm' => '1', 'rylx' => '0',
            'dwbm' => $college, 'dwmc' => $college, 'bjbm' => $code, 'bjmc' => '26Shared1']);
    }
    app(CounselorClassCatalog::class)->forget();
    $this->postJson('/counselors/'.$teacher->id.'/classes', ['class_name' => '26Shared1', 'class_code' => 'HOME_SHARED', 'college_code' => 'HOME'])->assertCreated();
    $this->postJson('/counselors/'.$teacher->id.'/classes', ['class_name' => '26Shared1', 'class_code' => 'LAW_SHARED', 'college_code' => 'LAW'])->assertCreated();
    expect($teacher->classAssignments()->count())->toBe(2);
});

it('filters family records and updates by the assigned delegated class', function () {
    $teacher = delegatedTeacher();
    CounselorCollegeDelegation::create(['employee_no' => 'delegate', 'college_code' => 'LAW', 'college_name' => 'LAW', 'is_active' => true]);
    CounselorClassAssignment::create(['user_id' => $teacher->id, 'counselor_cas_username' => 'delegate',
        'college_code' => 'LAW', 'class_code' => 'LAW1', 'class_name' => '26社工1', 'normalized_class_name' => '26社工1']);
    $owned = StudentFamily::create(['sync_key' => 'owned', 'stu_no' => 'LAW1', 'name' => '家长1', 'relationship' => '父亲']);
    $other = StudentFamily::create(['sync_key' => 'other', 'stu_no' => 'LAW2', 'name' => '家长2', 'relationship' => '父亲']);
    config(['cas.enabled' => true]);
    $this->actingAs($teacher)->withSession([config('cas.session_key') => ['user' => 'delegate']]);
    $this->getJson('/student-families/data')->assertOk()->assertJsonCount(1, 'data');
    $this->putJson('/student-families/data/'.$owned->id, ['work_unit' => '更新'])->assertOk();
    $this->putJson('/student-families/data/'.$other->id, ['work_unit' => '禁止'])->assertForbidden();
});
