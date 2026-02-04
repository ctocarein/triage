<?php

return [
    'referrals' => [
        'base_url' => env('AMBASSADOR_REFERRAL_BASE_URL', env('APP_URL', 'http://localhost:8000')),
        'landing_url' => env('AMBASSADOR_REFERRAL_LANDING_URL', env('APP_URL', 'http://localhost:8000')),
        'expired_url' => env('AMBASSADOR_REFERRAL_EXPIRED_URL', env('APP_URL', 'http://localhost:8000').'/referrals/expired'),
        'used_url' => env('AMBASSADOR_REFERRAL_USED_URL', env('APP_URL', 'http://localhost:8000').'/referrals/used'),
        'preview_image_url' => env('AMBASSADOR_REFERRAL_PREVIEW_IMAGE_URL', env('APP_URL', 'http://localhost:8000').'/referrals/preview.png'),
        'preview_site_name' => env('AMBASSADOR_REFERRAL_PREVIEW_SITE_NAME', 'CHEREH'),
        'default_expires_days' => env('AMBASSADOR_REFERRAL_DEFAULT_EXPIRES_DAYS', 30),
        'weekly_limit' => env('AMBASSADOR_REFERRAL_WEEKLY_LIMIT', 25),
        'cookie_name' => env('AMBASSADOR_REFERRAL_COOKIE', 'ambassador_referral'),
    ],
];
