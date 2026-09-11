<?php

use App\Auth\XgxtCasUserResolver;
use App\Models\User;

return [
    'enabled' => filter_var(env('CAS_ENABLED', false), FILTER_VALIDATE_BOOL),
    'server_url' => env('CAS_SERVER_URL', 'https://cas.paas.zufedfc.edu.cn/cas'),
    'session_key' => env('CAS_SESSION_KEY', 'cas_user'),
    'guard' => env('CAS_GUARD', 'web'),
    'http_timeout' => (int) env('CAS_HTTP_TIMEOUT', 10),
    'remember_minutes' => (int) env('CAS_REMEMBER_MINUTES', env('SESSION_LIFETIME', 120)),

    'routes' => [
        'prefix' => env('CAS_ROUTE_PREFIX', 'sso'),
        'middleware' => ['web'],
        'names' => [
            'login' => 'cas.login',
            'logout' => 'cas.logout',
            'slo' => 'cas.slo',
            'user_online_detect' => 'cas.user-online-detect',
        ],
    ],

    'user' => [
        'resolver' => XgxtCasUserResolver::class,
        'model' => User::class,
        'username_column' => 'cas_username',
        'name_attributes' => ['name', 'xm', 'cn', 'displayName', 'userName'],
        'email_attributes' => ['email', 'mail'],
        'email_domain' => env('CAS_EMAIL_DOMAIN', 'zufedfc.edu.cn'),
    ],
];
