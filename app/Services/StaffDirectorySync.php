<?php

namespace App\Services;

use App\Models\CounselorManagementPermission;
use App\Models\StaffMember;
use App\Models\StudentAccessPermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StaffDirectorySync
{
    private const MINIMUM_SOURCE_RATIO = 0.70;

    public function run(): array
    {
        $source = DB::connection('middata')->table('t_bd_zzqxryxx')
            ->select(['xgh', 'xm', 'dwbm', 'dwmc', 'dzyx', 'yddh'])
            ->whereNotNull('xgh')
            ->where('xgh', '!=', '')
            ->orderBy('xgh')
            ->get();

        if ($source->isEmpty()) {
            throw new RuntimeException('中间库在职教职工表为空，已终止同步且未修改本地数据。');
        }

        $duplicates = $source->groupBy(fn ($row) => trim((string) $row->xgh))->filter(fn ($rows) => $rows->count() > 1);
        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('中间库存在重复工号，已终止同步：'.$duplicates->keys()->take(10)->implode('、'));
        }

        $activeBefore = StaffMember::query()->where('is_active', true)->count();
        if ($activeBefore > 0 && $source->count() < (int) ceil($activeBefore * self::MINIMUM_SOURCE_RATIO)) {
            throw new RuntimeException("中间库人数由 {$activeBefore} 降至 {$source->count()}，低于安全阈值，已终止同步。 ");
        }

        $now = now();
        $sourceNumbers = $source->pluck('xgh')->map(fn ($value) => trim((string) $value))->values();
        $missingNumbers = StaffMember::query()->where('is_active', true)->whereNotIn('employee_no', $sourceNumbers)->pluck('employee_no');
        $remainingSuperAdmins = User::query()
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->when($missingNumbers->isNotEmpty(), fn ($query) => $query->whereNotIn('cas_username', $missingNumbers))
            ->count();

        if ($missingNumbers->isNotEmpty() && $remainingSuperAdmins === 0 && User::query()->where('role', User::ROLE_SUPER_ADMIN)->exists()) {
            throw new RuntimeException('本次同步将停用最后一位总管理员，已终止同步，请先授权另一位在职总管理员。');
        }

        $result = ['source' => $source->count(), 'created' => 0, 'updated' => 0, 'reactivated' => 0, 'deactivated' => 0];

        DB::transaction(function () use ($source, $missingNumbers, $now, &$result): void {
            foreach ($source as $row) {
                $employeeNo = trim((string) $row->xgh);
                $staff = StaffMember::query()->where('employee_no', $employeeNo)->lockForUpdate()->first();
                $wasInactive = $staff && ! $staff->is_active;
                $payload = [
                    'name' => trim((string) $row->xm),
                    'department_code' => $this->nullable($row->dwbm),
                    'department_name' => $this->nullable($row->dwmc),
                    'email' => $this->nullable($row->dzyx),
                    'phone' => $this->nullable($row->yddh),
                    'is_active' => true,
                    'last_seen_at' => $now,
                    'synced_at' => $now,
                ];

                if ($staff) {
                    $staff->update($payload);
                    $result['updated']++;
                    if ($wasInactive) {
                        $result['reactivated']++;
                        app(CounselorManagement::class)->log('staff.reactivated', $employeeNo, ['is_active' => false], ['is_active' => true]);
                    }
                } else {
                    StaffMember::query()->create(['employee_no' => $employeeNo] + $payload);
                    $result['created']++;
                }
            }

            foreach ($missingNumbers as $employeeNo) {
                $staff = StaffMember::query()->where('employee_no', $employeeNo)->lockForUpdate()->firstOrFail();
                $user = User::query()->where('cas_username', $employeeNo)->lockForUpdate()->first();
                $before = [
                    'staff_active' => $staff->is_active,
                    'role' => $user?->role,
                    'counselor_management_active' => CounselorManagementPermission::query()->where('employee_no', $employeeNo)->value('is_active'),
                    'student_access_active' => StudentAccessPermission::query()->where('employee_no', $employeeNo)->where('is_active', true)->count(),
                ];

                $staff->update(['is_active' => false, 'synced_at' => $now]);
                $user?->forceFill(['role' => User::ROLE_STAFF])->save();
                CounselorManagementPermission::query()->where('employee_no', $employeeNo)->update(['is_active' => false]);
                StudentAccessPermission::query()->where('employee_no', $employeeNo)->update(['is_active' => false]);
                app(CounselorManagement::class)->log('staff.deactivated', $employeeNo, $before, [
                    'staff_active' => false,
                    'role' => $user ? User::ROLE_STAFF : null,
                    'counselor_management_active' => false,
                    'student_access_active' => 0,
                ]);
                $result['deactivated']++;
            }
        });

        $result['active'] = StaffMember::query()->where('is_active', true)->count();
        $result['units'] = StaffMember::query()->where('is_active', true)->whereNotNull('department_code')->distinct()->count('department_code');

        return $result;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
