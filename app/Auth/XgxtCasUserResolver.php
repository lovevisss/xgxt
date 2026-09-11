<?php

namespace App\Auth;

use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Zufedfc\LaravelCas\Contracts\CasUserResolver;

class XgxtCasUserResolver implements CasUserResolver
{
    public function resolve(string $username, array $attributes): Authenticatable
    {
        $existingUser = User::query()->where('cas_username', $username)->first();
        $staff = StaffMember::query()->where('employee_no', $username)->first();

        if ($staff && ! $staff->is_active) {
            throw new AccessDeniedHttpException('当前账号已不在在职教职工目录中。');
        }

        $defaultRole = User::query()->count() === 0 ? User::ROLE_SUPER_ADMIN : User::ROLE_STAFF;

        $values = [
            'name' => $staff?->name ?? $this->firstAttribute($attributes, ['name', 'xm', 'cn', 'displayName', 'userName']) ?? $username,
            'email' => $existingUser?->email ?? $staff?->email ?? $this->firstAttribute($attributes, ['email', 'mail']) ?? "{$username}@zufedfc.edu.cn",
            'role' => $existingUser?->role ?: $defaultRole,
        ];

        if (! $existingUser) {
            $values['password'] = Hash::make(Str::random(40));
        }

        $departmentCode = $staff?->department_code ?? $this->firstAttribute($attributes, ['dwbm', 'department_code', 'deptCode', 'orgCode']);
        $departmentName = $staff?->department_name ?? $this->firstAttribute($attributes, ['dwmc', 'department_name', 'deptName', 'orgName']);

        if ($departmentCode !== null) {
            $values['dwbm'] = $departmentCode;
        }

        if ($departmentName !== null) {
            $values['dwmc'] = $departmentName;
        }

        return User::query()->updateOrCreate(['cas_username' => $username], $values);
    }

    private function firstAttribute(array $attributes, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $attributes[$key] ?? null;
            $value = is_array($value) ? ($value[0] ?? null) : $value;

            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
