<?php

declare(strict_types=1);

return [
    // ofrep/* for OpenFeature providers running in a browser (RFC-0005).
    'paths' => ['v1/*', 'ofrep/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        mb_trim(...),
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Accept-Language',
        'Authorization',
        'Content-Type',
        'Idempotency-Key',
        'Origin',
        'X-Request-Id',
        'X-Requested-With',
    ],

    'exposed_headers' => [
        'Content-Language',
        'Deprecation',
        'Idempotency-Key',
        'Idempotency-Replayed',
        'Link',
        'Retry-After',
        'Sunset',
        'X-Request-Id',
    ],

    'max_age' => 0,

    'supports_credentials' => false,
];
