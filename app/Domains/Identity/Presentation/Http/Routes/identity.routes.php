<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Identity\Presentation\Http\Controllers\IdentityAuthController;
use App\Domains\Identity\Presentation\Http\Controllers\IdentityController;
use App\Domains\Identity\Presentation\Http\Controllers\IdentityMembershipController;

Route::prefix('identity')->group(function () {
    Route::post('/register', [IdentityAuthController::class, 'register']);
    Route::post('/login', [IdentityAuthController::class, 'login']);
});

Route::prefix('identity')->middleware('auth:sanctum')->group(function () {
    Route::patch('/secret', [IdentityAuthController::class, 'setSecret']);
    Route::post('/unlock', [IdentityAuthController::class, 'unlock']);
    Route::post('/panic-lock', [IdentityAuthController::class, 'panicLock']);
    Route::get('/security/status', [IdentityAuthController::class, 'securityStatus']);
    Route::post('/logout', [IdentityAuthController::class, 'logout']);
    Route::get('/me', [IdentityController::class, 'me']);
    Route::get('/', [IdentityController::class, 'index'])->middleware('role:PlatformAdmin,OrgManager');
    Route::post('/', [IdentityController::class, 'store'])->middleware('role:PlatformAdmin');
    Route::get('/memberships', [IdentityMembershipController::class, 'index'])
        ->middleware('role:PlatformAdmin,OrgManager');
    Route::post('/memberships', [IdentityMembershipController::class, 'store'])
        ->middleware('role:PlatformAdmin,OrgManager');
    Route::get('/memberships/{membership}', [IdentityMembershipController::class, 'show'])
        ->middleware('role:PlatformAdmin,OrgManager');
    Route::patch('/memberships/{membership}', [IdentityMembershipController::class, 'update'])
        ->middleware('role:PlatformAdmin,OrgManager');
    Route::delete('/memberships/{membership}', [IdentityMembershipController::class, 'destroy'])
        ->middleware('role:PlatformAdmin,OrgManager');

    Route::get('/{identity}', [IdentityController::class, 'show'])->whereUuid('identity');
    Route::patch('/{identity}', [IdentityController::class, 'update'])->whereUuid('identity')
        ->middleware('role:PlatformAdmin');
    Route::delete('/{identity}', [IdentityController::class, 'destroy'])->whereUuid('identity')
        ->middleware('role:PlatformAdmin');
});
