<?php

use App\Domains\Profile\Presentation\Http\Controllers\MedicalProfileController;
use App\Domains\Profile\Presentation\Http\Controllers\PersonalProfileController;
use App\Domains\Profile\Presentation\Http\Controllers\ProfessionalProfileController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'identity/profile', 'middleware' => ['auth:sanctum', 'security.gate']], function () {
    Route::middleware('role:Beneficiary,FieldAgent,Ambassador')->group(function () {
        Route::get('/medical', [MedicalProfileController::class, 'index']);
        Route::post('/medical', [MedicalProfileController::class, 'store']);
        Route::put('/medical', [MedicalProfileController::class, 'update']);
        Route::get('/personal', [PersonalProfileController::class, 'index']);
        Route::post('/personal', [PersonalProfileController::class, 'store']);
        Route::put('/personal', [PersonalProfileController::class, 'update']);
    });

    Route::middleware('role:Practitioner,OrgManager,PlatformAdmin,System')->group(function () {
        Route::get('/pro', [ProfessionalProfileController::class, 'index']);
        Route::post('/pro', [ProfessionalProfileController::class, 'store']);
        Route::put('/pro', [ProfessionalProfileController::class, 'update']);
    });
});
