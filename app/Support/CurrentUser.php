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
        if (! config('cas.enabled') && self::get() === null) {
            return true;
        }

        return (bool) self::get()?->canManageStudentDepartment($departmentCode);
    }
}
