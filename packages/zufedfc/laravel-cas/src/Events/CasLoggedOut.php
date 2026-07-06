<?php

namespace Zufedfc\LaravelCas\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

class CasLoggedOut
{
    use Dispatchable;

    public function __construct(
        public readonly ?string $username,
        public readonly Request $request,
        public readonly string $reason = 'logout',
    ) {}
}
