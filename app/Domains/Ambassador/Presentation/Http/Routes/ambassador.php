<?php

use App\Domains\Ambassador\Presentation\Http\Controllers\AmbassadorBadgeController;
use App\Domains\Ambassador\Presentation\Http\Controllers\AmbassadorController;
use App\Domains\Ambassador\Presentation\Http\Controllers\ReferralController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'ambassador', 'middleware' => ['auth:sanctum']], function () {
    Route::post('/activate', [AmbassadorController::class, 'activate'])->middleware('role:PlatformAdmin');

    Route::middleware('role:Ambassador')->group(function () {
        Route::get('/profile', [AmbassadorController::class, 'profile']);
        Route::post('/deactivate', [AmbassadorController::class, 'deactivate']);
        Route::get('/metrics', [AmbassadorController::class, 'metrics']);
        Route::get('/dashboard', [AmbassadorController::class, 'dashboard']);
        Route::post('/badges/recompute', [AmbassadorController::class, 'recomputeBadges']);
        Route::post('/level/recompute', [AmbassadorController::class, 'recomputeLevel']);

        Route::get('/referrals', [ReferralController::class, 'index']);
        Route::post('/referrals', [ReferralController::class, 'store']);
        Route::post('/referrals/{referral}/revoke', [ReferralController::class, 'revoke']);

        Route::get('/badges', [AmbassadorBadgeController::class, 'index']);
    });
});
