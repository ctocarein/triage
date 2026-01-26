<?php

use App\Domains\Profile\Controllers\Identity\MedicalProfileController;
use App\Domains\Profile\Controllers\Identity\PersonalProfileController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'identity/profile', 'middleware' => 'auth:sanctum'], function () {
    Route::get('/medical', [MedicalProfileController::class, 'index']);
    Route::post('/medical', [MedicalProfileController::class, 'store']);
    Route::put('/medical', [MedicalProfileController::class, 'update']);
    Route::get('/personal', [PersonalProfileController::class, 'index']);
    Route::post('/personal', [PersonalProfileController::class, 'store']);
    Route::put('/personal', [PersonalProfileController::class, 'update']);
});
