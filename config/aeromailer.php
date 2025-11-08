<?php

return [
    // API key used to authenticate with client's HTTP mail API
    'api_key' => env('AEROMAIL_API_KEY', ''),

    // Base endpoint of the mail provider (no trailing slash)
    'endpoint' => env('AEROMAIL_ENDPOINT', 'https://api.example-mail.local/v1'),

    // Whether to send Authorization as "Bearer <key>" or use a custom header
    'auth' => [
        'type' => env('AEROMAIL_AUTH_TYPE', 'bearer'), // 'bearer' or 'header'
        'header_name' => env('AEROMAIL_AUTH_HEADER', 'X-Api-Key'),
    ],

    // Default top-level payload fields sent with every send request
    'defaults' => [
        // e.g. 'from_domain' => 'example.com'
    ],

    // Guzzle client options
    'guzzle' => [
        'timeout' => 15,
        'connect_timeout' => 5,
        // 'verify' => true,
    ],

    // Retry behaviour for transient errors
    'retries' => [
        'max_attempts' => 4,
        'initial_delay_ms' => 250, // backoff base in ms
        'max_delay_ms' => 5000,
    ],

    // The path appended to endpoint to send mail (customize to client's API)
    'send_path' => env('AEROMAIL_SEND_PATH', '/send'),
];
