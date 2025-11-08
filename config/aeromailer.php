<?php
return [
    'endpoint' => env('AEROMAIL_ENDPOINT', 'https://api.your-backend.com'),
    'api_key' => env('AEROMAIL_CLIENT_KEY'),
    'http' => [
        'timeout' => env('AEROMAIL_HTTP_TIMEOUT', 15),
    ],
];
