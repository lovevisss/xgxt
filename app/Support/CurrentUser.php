<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CurrentUser
{
    public static function get(): ?User
    {
        $authUser = Auth::user();
        if ($authUser instanceof User) {
            return $authUser;
        }

        $casUser = session(config('cas.session_key'));
        $username = is_array($casUser) ? (string) ($casUser['user'] ?? '') : '';
        if ($username === '') {
            return null;
        }

        return User::query()->where('cas_username', $username)->first();
    }

    public static function canManageDepartment(?string $departmentCode): bool
    {
        return app(\App\Services\StudentAccess::class)->canEditFamily($departmentCode);
    }

    public static function canManageStudent(?\App\Models\Student $student): bool
    {
        return app(\App\Services\StudentAccess::class)->canEditFamilyStudent($student);
    }
}
