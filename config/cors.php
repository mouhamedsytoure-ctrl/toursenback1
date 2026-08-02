<?php

// CORS_ALLOWED_ORIGINS : liste separee par des virgules des origines autorisees
// (le ou les domaines du front Angular). Ex: http://localhost:4200,https://sunnu-immo.com
$origines = array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:4200')))));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $origines,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];