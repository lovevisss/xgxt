<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentAccessPermission;
use App\Models\User;
use App\Support\CurrentUser;

class StudentAccess
{
    public function scope(?User $user = null): array
    {
        if (! config('cas.enabled')) {
            return ['all' => true, 'departments' => []];
        }
        $user ??= CurrentUser::get();
        if (! $user || $user->isInactiveStaffMember()) {
            return ['all' => false, 'departments' => []];
        }
        if ($user->isAdmin() || $user->role === User::ROLE_STUDENT_AFFAIRS) {
            return ['all' => true, 'departments' => []];
        }
        $permissions = $user->studentAccessPermissions()->where('is_active', true)->get();
        $departments = $permissions->where('scope_type', StudentAccessPermission::SCOPE_COLLEGE)->pluck('department_code');
        if ($user->isCounselor() && filled($user->dwbm)) {
            $departments->push((string) $user->dwbm);
        }

        return [
            'all' => $permissions->contains('scope_type', StudentAccessPermission::SCOPE_ALL),
            'departments' => $departments->filter(fn ($code) => filled($code))->map(fn ($code) => (string) $code)->unique()->values()->all(),
        ];
    }

    public function apply($query, string $column = 'dwbm'): void
    {
        $scope = $this->scope();
        if (! $scope['all']) {
            $query->whereIn($column, $scope['departments']);
        }
    }

    public function canViewStudent(?Student $student, ?User $user = null): bool
    {
        if (! $student) return false;
        $scope = $this->scope($user);

        return $scope['all'] || in_array((string) $student->dwbm, $scope['departments'], true);
    }

    public function canEditStudents(): bool
    {
        return ! config('cas.enabled') || (bool) ($this->activeUser()?->isAdmin());
    }

    public function canEditFamily(?string $department, ?User $user = null): bool
    {
        if (! config('cas.enabled')) return true;
        $user ??= $this->activeUser();

        return $user && ! $user->isInactiveStaffMember() && ($user->isAdmin() || ($user->isCounselor() && filled($user->dwbm) && filled($department) && (string) $user->dwbm === (string) $department));
    }

    public function activeUser(): ?User
    {
        $user = CurrentUser::get();

        return $user && ! $user->isInactiveStaffMember() ? $user : null;
    }

    public function capabilities(): array
    {
        $scope = $this->scope();
        $user = $this->activeUser();
        $admin = $this->canEditStudents();

        return [
            'students' => $scope['all'] || count($scope['departments']) > 0,
            'editStudents' => $admin,
            'editFamilies' => $admin || (bool) ($user?->isCounselor() && filled($user->dwbm)),
            'businessAdmin' => $admin,
            'systemAdmin' => ! config('cas.enabled') || (bool) $user?->isSuperAdmin(),
            'counselors' => $admin || (bool) ($user && ($user->isCounselor() || app(CounselorManagement::class)->permission())),
        ];
    }
}
