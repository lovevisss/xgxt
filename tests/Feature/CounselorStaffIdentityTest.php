<?php

use App\Models\CounselorClassAssignment;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\User;
use App\Services\CounselorStaffIdentityMerge;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function identityFixture(): array
{
    Student::create(['xgh' => 'student', 'xm' => 'Student', 'xbm' => '1', 'rylx' => '0',
        'dwbm' => 'FIN', 'dwmc' => 'Finance', 'bjbm' => 'FIN1', 'bjmc' => '26Finance1']);
    $old = User::factory()->create(['cas_username' => '20210303', 'name' => '罗静怡', 'role' => User::ROLE_COUNSELOR, 'dwbm' => 'FIN', 'dwmc' => 'Finance']);
    $current = User::factory()->create(['cas_username' => '20250066', 'name' => '罗静怡', 'role' => User::ROLE_COUNSELOR, 'dwbm' => 'FIN', 'dwmc' => 'Finance']);
    StaffMember::create(['employee_no' => '20250066', 'name' => '罗静怡', 'department_code' => 'FIN', 'department_name' => 'Finance', 'is_active' => true]);
    foreach (range(1, 7) as $n) {
        foreach ([[$old, '22'], [$current, '26']] as [$user, $grade]) {
            CounselorClassAssignment::create(['user_id' => $user->id, 'counselor_cas_username' => $user->cas_username,
                'college_code' => 'FIN', 'college_name' => 'Finance', 'class_code' => $grade.'FIN'.$n,
                'class_name' => $grade.'Finance'.$n, 'normalized_class_name' => strtolower($grade.'Finance'.$n)]);
        }
    }

    return [$old, $current];
}

it('previews and merges only confirmed employee numbers, preserving fourteen classes and remaining idempotent', function () {
    [$old, $current] = identityFixture();
    $this->artisan('counselors:merge-staff-identity', ['old' => '20210303', 'current' => '20250066', '--preview' => true])->assertExitCode(0);
    expect($old->classAssignments()->count())->toBe(7);
    $this->artisan('counselors:merge-staff-identity', ['old' => '20210303', 'current' => '20250066'])->assertExitCode(0);
    expect($old->classAssignments()->count())->toBe(0);
    expect($current->classAssignments()->count())->toBe(14);
    expect($old->refresh()->role)->toBe(User::ROLE_STAFF);
    expect(StaffMember::where('employee_no', '20210303')->value('is_active'))->toBeFalsy();
    $this->artisan('counselors:merge-staff-identity', ['old' => '20210303', 'current' => '20250066'])->assertExitCode(0);
    expect($current->classAssignments()->count())->toBe(14);
    $this->assertDatabaseCount('counselor_class_assignments', 14);
    $this->assertDatabaseHas('counselor_management_logs', ['action' => 'staff.identity.merge', 'subject' => '20210303->20250066']);
});

it('rolls back a merge when a class is already owned by another account', function () {
    [$old, $current] = identityFixture();
    $other = User::factory()->create(['cas_username' => 'other', 'dwbm' => 'FIN']);
    CounselorClassAssignment::create(['user_id' => $other->id, 'counselor_cas_username' => 'other',
        'college_code' => 'FIN', 'class_code' => '22FIN1', 'class_name' => '22Finance1', 'normalized_class_name' => '22finance1']);
    $this->artisan('counselors:merge-staff-identity', ['old' => '20210303', 'current' => '20250066'])->assertExitCode(1);
    expect($old->classAssignments()->count())->toBe(7);
    expect($current->classAssignments()->count())->toBe(7);
    $this->assertDatabaseMissing('counselor_management_logs', ['action' => 'staff.identity.merge']);
});
