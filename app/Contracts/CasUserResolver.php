<?php

namespace App\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface CasUserResolver
{
    public function resolve(string $username, array $attributes): Authenticatable;
}
