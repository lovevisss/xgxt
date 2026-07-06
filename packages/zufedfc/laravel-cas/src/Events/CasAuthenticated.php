<?php

namespace Zufedfc\LaravelCas\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

class CasAuthenticated
{
    use Dispatchable;

    public function __construct(
        public readonly Authenticatable $user,
        public readonly string $username,
        public readonly array $attributes,
        public readonly string $ticket,
        public readonly Request $request,
    ) {}
}
