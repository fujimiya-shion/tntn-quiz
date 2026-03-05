<?php

return [
    'host_account' => [
        'name' => env('QUIZ_HOST_NAME', 'Quiz Host'),
        'email' => env('QUIZ_HOST_EMAIL', 'host@quiz.local'),
        'password' => env('QUIZ_HOST_PASSWORD'),
    ],
    'host_auth' => [
        'cookie_name' => env('QUIZ_HOST_AUTH_COOKIE', 'quiz_host_access_token'),
        'ttl_minutes' => (int) env('QUIZ_HOST_AUTH_TTL_MINUTES', 480),
    ],
];
