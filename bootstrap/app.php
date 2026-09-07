<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\ApiCorsFallback;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(ApiCorsFallback::class);
        $middleware->prepend(HandleCors::class);

        // Middleware for the application
        $middleware->alias([
            'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
            'role' => \App\Http\Middleware\EnsureIdentityRole::class,
            'security.gate' => \App\Http\Middleware\SecurityGateMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
