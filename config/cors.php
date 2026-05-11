<?php

declare(strict_types=1);

$origins = env('CORS_ALLOWED_ORIGINS', '*');

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins === '*'
        ? ['*']
        : array_values(array_filter(array_map('trim', explode(',', (string) $origins)))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
