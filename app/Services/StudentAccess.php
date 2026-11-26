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
            return ['all' => true, 'departments' => [], 'classes' => []];
        }
        $user ??= CurrentUser::get();
        if (! $user || $user->isInactiveStaffMember()) {
            return ['all' => false, 'departments' => [], 'classes' => []];
        }
        if ($user->isAdmin() || $user->role === User::ROLE_STUDENT_AFFAIRS) {
            return ['all' => true, 'departments' => [], 'classes' => []];
        }
        $permissions = $user->studentAccessPermissions()->where('is_active', true)->get();
        $departments = $permissions->where('scope_type', StudentAccessPermission::SCOPE_COLLEGE)->pluck('department_code');
        if ($user->isCounselor() && filled($user->dwbm)) {
            $departments->push((string) $user->dwbm);
        }
        $delegated = $user->isCounselor()
            ? $user->classAssignments()->whereNotNull('class_code')->where('class_code', '!=', '')
                ->get()->filter(fn ($row) => $row->college_code !== $user->dwbm
                    && app(CounselorCollegeEligibility::class)->allows($user, $row->college_code))
                ->map(fn ($row) => ['college_code' => $row->college_code, 'class_code' => $row->class_code])->unique()->values()->all()
            : [];

        return [
            'all' => $permissions->contains('scope_type', StudentAccessPermission::SCOPE_ALL),
            'departments' => $departments->filter(fn ($code) => filled($code))->map(fn ($code) => (string) $code)->unique()->values()->all(),
            'classes' => $delegated,
        ];
    }

    public function apply($query, string $column = 'dwbm'): void
    {
        $scope = $this->scope();
        if (! $scope['all']) {
            $classColumn = str_contains($column, '.') ? substr($column, 0, strrpos($column, '.')).'.bjbm' : 'bjbm';
            $query->where(function ($visible) use ($scope, $column, $classColumn) {
                $visible->whereIn($column, $scope['departments']);
                foreach ($scope['classes'] ?? [] as $class) {
                    $visible->orWhere(fn ($pair) => $pair->where($column, $class['college_code'])
                        ->where($classColumn, $class['class_code']));
                }
            });
        }
    }

    public function canViewStudent(?Student $student, ?User $user = null): bool
    {
        if (! $student) return false;
        $scope = $this->scope($user);

        return $scope['all'] || in_array((string) $student->dwbm, $scope['departments'], true)
            || collect($scope['classes'] ?? [])->contains(fn ($class) => $class['college_code'] === (string) $student->dwbm
                && $class['class_code'] === (string) $student->bjbm);
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

    public function canEditFamilyStudent(?Student $student, ?User $user = null): bool
    {
        if (! config('cas.enabled')) return true;
        if (! $student) return false;
        if ($this->canEditFamily($student->dwbm, $user)) return true;
        $user ??= $this->activeUser();

        return $user && $user->isCounselor() && ! $user->isInactiveStaffMember()
            && collect($this->scope($user)['classes'] ?? [])->contains(fn ($class) => $class['college_code'] === (string) $student->dwbm
                && $class['class_code'] === (string) $student->bjbm);
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
            'students' => $scope['all'] || count($scope['departments']) > 0 || count($scope['classes'] ?? []) > 0,
            'editStudents' => $admin,
            'editFamilies' => $admin || (bool) ($user?->isCounselor() && filled($user->dwbm)),
            'businessAdmin' => $admin,
            'systemAdmin' => ! config('cas.enabled') || (bool) $user?->isSuperAdmin(),
            'counselors' => $admin || (bool) ($user && ($user->isCounselor() || app(CounselorManagement::class)->permission())),
        ];
    }
}
