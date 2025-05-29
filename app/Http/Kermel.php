<?php

namespace App\Http;

use App\Http\Middleware\VerifyDeviceUuid;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use App\Http\Middleware\EnsureHasActiveEvaluationSession;

class Kernel extends HttpKernel
{
    /**
     * Global HTTP middleware stack (executed on every request).
     */
    protected $middleware = [
        \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * Route middleware groups.
     */
    protected $middlewareGroups = [
        'api' => [
            // Laravel Sanctum pour l'auth
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,

            // Protection contre les abus
            'throttle:api',

            // Injection automatique des bindings (ex: {user})
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * Route middleware (assignables individuellement dans les routes).
     */
    protected $routeMiddleware = [
        'has.active.session' => EnsureHasActiveEvaluationSession::class,
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,

        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,

        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,

        // ✅ Ton middleware perso : vérifie que le token vient du bon appareil
        'verify.device' => VerifyDeviceUuid::class,
    ];
}
