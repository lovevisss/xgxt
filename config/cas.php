<?php

return [
    'enabled' => (bool) env('CAS_ENABLED', false),
    'server_url' => env('CAS_SERVER_URL', 'https://cas.paas.zufedfc.edu.cn/cas'),
    'session_key' => env('CAS_SESSION_KEY', 'cas_user'),
];

