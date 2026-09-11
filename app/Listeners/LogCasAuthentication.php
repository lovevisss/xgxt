<?php

namespace App\Listeners;

use App\Events\CasAuthenticated;
use App\Models\UserLoginLog;

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
