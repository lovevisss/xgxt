<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Zufedfc\LaravelCas\Contracts\CasUserResolver;

class XgxtCasUserResolver implements CasUserResolver
{
    public function resolve(string $username, array $attributes): Authenticatable
    {
        $existingUser = User::query()->where('cas_username', $username)->first();
        $defaultRole = User::query()->count() === 0 ? User::ROLE_SUPER_ADMIN : User::ROLE_STAFF;

        $values = [
            'name' => $this->firstAttribute($attributes, ['name', 'xm', 'cn', 'displayName', 'userName']) ?? $username,
            'email' => $this->firstAttribute($attributes, ['email', 'mail']) ?? "{$username}@zufedfc.edu.cn",
            'password' => Hash::make(Str::random(40)),
            'role' => $existingUser?->role ?: $defaultRole,
        ];

        $departmentCode = $this->firstAttribute($attributes, ['dwbm', 'department_code', 'deptCode', 'orgCode']);
        $departmentName = $this->firstAttribute($attributes, ['dwmc', 'department_name', 'deptName', 'orgName']);

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
