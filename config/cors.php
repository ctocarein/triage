<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        'https://triage.carein.cloud',
        'https://clinique.carein.pro',
        'https://open.chereh.com',
        'https://on.chereh.com',
        'https://on.dev.chereh.com',
        'http://localhost:3000',
        'http://localhost:3001',
    ],

    'allowed_origins_patterns' => [
        // Autorise toutes les origines LAN (192.168.x.x) sur n'importe quel port — dev uniquement
        '#^http://192\.168\.\d+\.\d+(:\d+)?$#',
        // Autorise localhost sur n'importe quel port (Flutter Web dev server) — dev uniquement
        '#^http://localhost(:\d+)?$#',
    ],

    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With', 'Idempotency-Key', 'idempotency-key', 'X-Idempotency-Key'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
