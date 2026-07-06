<?php

namespace App\Listeners;

use App\Models\UserLoginLog;
use Zufedfc\LaravelCas\Events\CasAuthenticated;

class LogCasAuthentication
{
    public function handle(CasAuthenticated $event): void
    {
        UserLoginLog::query()->create([
            'user_id' => $event->user->getAuthIdentifier(),
            'cas_username' => $event->username,
            'name' => data_get($event->user, 'name'),
            'logged_in_at' => now(),
            'ip_address' => $event->request->ip(),
            'user_agent' => substr((string) $event->request->userAgent(), 0, 1000),
        ]);
    }
}
