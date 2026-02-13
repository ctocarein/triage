<?php

$mandatoryOrigins = [
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'https://triage.carein.cloud',
    'https://on.dev.chereh.com',
];

$envOriginsRaw = env('CORS_ALLOWED_ORIGINS', '');
$envOrigins = [];

if (is_string($envOriginsRaw) && $envOriginsRaw !== '') {
    $decoded = json_decode($envOriginsRaw, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $envOrigins = $decoded;
    } else {
        $envOrigins = explode(',', $envOriginsRaw);
    }
}

$normalizeOrigin = static fn ($origin) => rtrim(trim((string) $origin), '/');

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */
    'paths' => ['*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Keep mandatory explicit origins and optionally append CORS_ALLOWED_ORIGINS.
    'allowed_origins' => array_values(array_unique(array_filter(array_map(
        $normalizeOrigin,
        array_merge($mandatoryOrigins, $envOrigins)
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
