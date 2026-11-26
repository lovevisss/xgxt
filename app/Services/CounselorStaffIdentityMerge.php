<?php

namespace App\Services;

use App\Models\CounselorClassAssignment;
use App\Models\CounselorManagementPermission;
use App\Models\StaffMember;
use App\Models\StudentAccessPermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CounselorStaffIdentityMerge
{
    public function inspect(string $oldNumber, string $currentNumber): array
    {
        if ($oldNumber === $currentNumber) {
            throw new RuntimeException('新旧工号不能相同。');
        }
        $old = User::where('cas_username', $oldNumber)->firstOrFail();
        $current = User::where('cas_username', $currentNumber)->firstOrFail();
        $staff = StaffMember::where('employee_no', $currentNumber)->where('is_active', true)->first();
        if (! $staff || ! filled($staff->department_code)) {
            throw new RuntimeException('新工号不在有效的在职教职工目录中。');
        }
        if (StaffMember::where('employee_no', $oldNumber)->where('is_active', true)->exists()) {
            throw new RuntimeException('旧工号仍在在职目录中，不能合并。');
        }
        if (trim($old->name) !== trim($staff->name) || trim($current->name) !== trim($staff->name)
            || $old->dwbm !== $staff->department_code || $current->dwbm !== $staff->department_code) {
            throw new RuntimeException('新旧账号与在职目录的姓名或学院不一致，请先人工核实。');
        }

        $auditSubject = $oldNumber.'->'.$currentNumber;
        $alreadyMerged = DB::table('counselor_management_logs')->where('action', 'staff.identity.merge')->where('subject', $auditSubject)->exists();
        $oldAssignments = $old->classAssignments()->orderBy('id')->get();
        if ($alreadyMerged && $oldAssignments->isEmpty()) {
            return ['status' => 'already_merged', 'old' => $oldNumber, 'current' => $currentNumber, 'classes' => []];
        }
        if ($alreadyMerged) {
            throw new RuntimeException('曾合并过该工号，但旧账号仍有带班关系，请人工检查。');
        }
        if ($oldAssignments->isEmpty()) {
            throw new RuntimeException('旧账号没有可合并的带班关系。');
        }
        $otherAssignments = CounselorClassAssignment::where('counselor_cas_username', '!=', $oldNumber)->get();
        $ownership = app(CounselorClassOwnership::class);
        foreach ($oldAssignments as $assignment) {
            if ($assignment->college_code !== $staff->department_code) {
                throw new RuntimeException($assignment->class_name.' 不属于在职账号的学院，已停止合并。');
            }
            if ($otherAssignments->contains(fn ($other) => $ownership->matches($assignment, $other))) {
                throw new RuntimeException($assignment->class_name.' 已有其他辅导员带班，已停止合并。');
            }
            if ($current->classAssignments()->where('normalized_class_name', $assignment->normalized_class_name)->exists()) {
                throw new RuntimeException($assignment->class_name.' 与新账号已有班级名称冲突，已停止合并。');
            }
        }

        return ['status' => 'ready', 'old' => $oldNumber, 'current' => $currentNumber,
            'name' => $staff->name, 'college' => $staff->department_name,
            'classes' => $oldAssignments->map(fn ($row) => ['id' => $row->id, 'class_code' => $row->class_code, 'class_name' => $row->class_name])->all()];
    }

    public function merge(string $oldNumber, string $currentNumber): array
    {
        $result = DB::transaction(function () use ($oldNumber, $currentNumber) {
            User::whereIn('cas_username', [$oldNumber, $currentNumber])->orderBy('cas_username')->lockForUpdate()->get();
            CounselorClassAssignment::whereIn('counselor_cas_username', [$oldNumber, $currentNumber])->lockForUpdate()->get();
            $plan = $this->inspect($oldNumber, $currentNumber);
            if ($plan['status'] === 'already_merged') return $plan;

            $old = User::where('cas_username', $oldNumber)->firstOrFail();
            $current = User::where('cas_username', $currentNumber)->firstOrFail();
            $staff = StaffMember::where('employee_no', $currentNumber)->where('is_active', true)->firstOrFail();
            $before = ['old_user' => $old->toArray(), 'current_user' => $current->toArray(),
                'old_assignments' => $old->classAssignments()->get()->toArray(),
                'current_assignments' => $current->classAssignments()->get()->toArray(),
                'current_directory' => $staff->toArray()];
            CounselorClassAssignment::where('counselor_cas_username', $oldNumber)
                ->update(['user_id' => $current->id, 'counselor_cas_username' => $currentNumber]);
            StaffMember::updateOrCreate(['employee_no' => $oldNumber], [
                'name' => $old->name, 'department_code' => $old->dwbm, 'department_name' => $old->dwmc,
                'is_active' => false, 'synced_at' => now(),
            ]);
            $old->forceFill(['role' => User::ROLE_STAFF])->save();
            $current->forceFill(['name' => $staff->name, 'dwbm' => $staff->department_code, 'dwmc' => $staff->department_name])->save();
            CounselorManagementPermission::where('employee_no', $oldNumber)->update(['is_active' => false]);
            StudentAccessPermission::where('employee_no', $oldNumber)->update(['is_active' => false]);
            app(CounselorManagement::class)->log('staff.identity.merge', $oldNumber.'->'.$currentNumber, $before,
                ['old_user' => $old->fresh()->toArray(), 'current_user' => $current->fresh()->toArray(),
                    'current_assignments' => $current->classAssignments()->get()->toArray(),
                    'transferred' => count($plan['classes'])]);

            return $plan + ['transferred' => count($plan['classes'])];
        });
        app(CounselorClassCatalog::class)->forget();

        return $result;
    }
}
