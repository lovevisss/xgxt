<?php

namespace App\Services;

use App\Models\CounselorCollegeDelegation;
use App\Models\StaffMember;
use App\Models\User;

class CounselorCollegeEligibility
{
    public function allows(User $user, string $collegeCode): bool
    {
        $staff = StaffMember::where('employee_no', $user->cas_username)->where('is_active', true)->first();
        if (! $staff) return false;
        if ($staff->department_code === $collegeCode) return true;

        return CounselorCollegeDelegation::where('employee_no', $user->cas_username)
            ->where('college_code', $collegeCode)->where('is_active', true)->exists();
    }

    public function allowsNumber(string $employeeNo, string $collegeCode): bool
    {
        $staff = StaffMember::where('employee_no', $employeeNo)->where('is_active', true)->first();
        if (! $staff) return false;
        if ($staff->department_code === $collegeCode) return true;

        return CounselorCollegeDelegation::where('employee_no', $employeeNo)
            ->where('college_code', $collegeCode)->where('is_active', true)->exists();
    }

    public function codes(User $user): array
    {
        $staff = StaffMember::where('employee_no', $user->cas_username)->where('is_active', true)->first();
        if (! $staff) return [];

        return collect([$staff->department_code])->merge($user->counselorDelegations()->where('is_active', true)->pluck('college_code'))
            ->filter()->unique()->values()->all();
    }
}
