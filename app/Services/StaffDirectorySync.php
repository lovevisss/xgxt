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
        $sourceByNameCollege = $source->groupBy(fn ($row) => trim((string) $row->xm).'|'.trim((string) $row->dwbm));
        $missingNumbers = StaffMember::query()->where('is_active', true)->whereNotIn('employee_no', $sourceNumbers)->pluck('employee_no')
            ->merge(User::query()->where(fn ($query) => $query->where('role', User::ROLE_COUNSELOR)->orWhereHas('classAssignments'))
                ->whereNotIn('cas_username', $sourceNumbers)->pluck('cas_username'))->unique()->values();
        $remainingSuperAdmins = User::query()
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->when($missingNumbers->isNotEmpty(), fn ($query) => $query->whereNotIn('cas_username', $missingNumbers))
            ->count();

        if ($missingNumbers->isNotEmpty() && $remainingSuperAdmins === 0 && User::query()->where('role', User::ROLE_SUPER_ADMIN)->exists()) {
            throw new RuntimeException('本次同步将停用最后一位总管理员，已终止同步，请先授权另一位在职总管理员。');
        }

        $result = ['source' => $source->count(), 'created' => 0, 'updated' => 0, 'reactivated' => 0,
            'deactivated' => 0, 'identity_pending' => 0, 'assignments_released' => 0];

        DB::transaction(function () use ($source, $sourceByNameCollege, $missingNumbers, $now, &$result): void {
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
                $user = User::where('cas_username', $employeeNo)->first();
                if ($user && ($user->isCounselor() || $user->classAssignments()->exists())) {
                    $removed = $payload['department_code'] === null ? $user->classAssignments()->get()
                        : $user->classAssignments()->where('college_code', '!=', $payload['department_code'])->get();
                    if ($removed->isNotEmpty()) {
                        $removed->each->delete();
                        $result['assignments_released'] += $removed->count();
                        app(CounselorManagement::class)->log('staff.college.changed', $employeeNo,
                            ['college_code' => $user->dwbm, 'assignments' => $removed->toArray()],
                            ['college_code' => $payload['department_code']]);
                    }
                    $user->forceFill(['name' => $payload['name'], 'dwbm' => $payload['department_code'],
                        'dwmc' => $payload['department_name']])->save();
                }
            }

            foreach ($missingNumbers as $employeeNo) {
                $staff = StaffMember::query()->where('employee_no', $employeeNo)->lockForUpdate()->first();
                $user = User::query()->where('cas_username', $employeeNo)->lockForUpdate()->first();
                $candidates = $user ? $sourceByNameCollege->get(trim($user->name).'|'.trim((string) $user->dwbm), collect())
                    ->filter(fn ($row) => trim((string) $row->xgh) !== $employeeNo)->pluck('xgh')->values()->all() : [];
                $pending = $candidates !== [] && ($user?->isCounselor() || $user?->classAssignments()->exists());
                $assignments = $user?->classAssignments()->get() ?? collect();
                $before = [
                    'staff_active' => $staff?->is_active,
                    'role' => $user?->role,
                    'counselor_management_active' => CounselorManagementPermission::query()->where('employee_no', $employeeNo)->value('is_active'),
                    'student_access_active' => StudentAccessPermission::query()->where('employee_no', $employeeNo)->where('is_active', true)->count(),
                    'assignments' => $assignments->toArray(),
                ];

                if ($staff) {
                    $staff->update(['is_active' => false, 'synced_at' => $now]);
                } elseif ($user) {
                    StaffMember::create(['employee_no' => $employeeNo, 'name' => $user->name,
                        'department_code' => $user->dwbm, 'department_name' => $user->dwmc,
                        'is_active' => false, 'synced_at' => $now]);
                }
                $user?->forceFill(['role' => User::ROLE_STAFF])->save();
                CounselorManagementPermission::query()->where('employee_no', $employeeNo)->update(['is_active' => false]);
                StudentAccessPermission::query()->where('employee_no', $employeeNo)->update(['is_active' => false]);
                if (! $pending) {
                    $assignments->each->delete();
                    $result['assignments_released'] += $assignments->count();
                } else {
                    $result['identity_pending']++;
                }
                if ($before['staff_active'] || $before['role'] !== User::ROLE_STAFF || $before['counselor_management_active'] || $before['student_access_active'] || (! $pending && $assignments->isNotEmpty())) {
                    app(CounselorManagement::class)->log($pending ? 'staff.identity_pending' : 'staff.deactivated', $employeeNo, $before, [
                    'staff_active' => false,
                    'role' => $user ? User::ROLE_STAFF : null,
                    'counselor_management_active' => false,
                    'student_access_active' => 0,
                    'possible_current_numbers' => $candidates,
                    'assignments_retained' => $pending ? $assignments->count() : 0,
                    ]);
                }
                if (! $pending) $result['deactivated']++;
            }
        });

        if ($result['assignments_released'] > 0) {
            app(CounselorClassCatalog::class)->forget();
        }

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
