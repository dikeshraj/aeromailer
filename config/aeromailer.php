<?php

return [
    'endpoint' => env('AEROMAIL_ENDPOINT', 'https://api.yourdomain.com'),
    'api_key' => env('AEROMAIL_CLIENT_KEY'),
    'queue' => env('AEROMAIL_QUEUE', 'emails'),
];
