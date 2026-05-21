<?php

return [
    // Support both CAS_ENABLED (preferred) and CAS_ENABLE (legacy/mistyped).
    'enabled' => filter_var(env('CAS_ENABLED', env('CAS_ENABLE', false)), FILTER_VALIDATE_BOOL),
    'server_url' => env('CAS_SERVER_URL', 'https://cas.paas.zufedfc.edu.cn/cas'),
    'session_key' => env('CAS_SESSION_KEY', 'cas_user'),
];

