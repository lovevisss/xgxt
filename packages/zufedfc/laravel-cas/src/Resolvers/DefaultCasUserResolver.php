<?php

namespace Zufedfc\LaravelCas\Resolvers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Zufedfc\LaravelCas\Contracts\CasUserResolver;

class DefaultCasUserResolver implements CasUserResolver
{
    public function resolve(string $username, array $attributes): Authenticatable
    {
        $modelClass = config('cas.user.model');
        $usernameColumn = (string) config('cas.user.username_column', 'cas_username');

        if (! is_string($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            throw new RuntimeException('CAS user model must be an Eloquent model.');
        }

        $name = $this->firstAttribute($attributes, config('cas.user.name_attributes', [])) ?? $username;
        $email = $this->firstAttribute($attributes, config('cas.user.email_attributes', []))
            ?? $username.'@'.config('cas.user.email_domain', 'cas.local');

        /** @var Model&Authenticatable $user */
        $user = $modelClass::query()->updateOrCreate(
            [$usernameColumn => $username],
            [
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
            ],
        );

        if (! $user instanceof Authenticatable) {
            throw new RuntimeException('CAS user model must implement Authenticatable.');
        }

        return $user;
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
