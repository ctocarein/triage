<?php

use App\Domains\Organization\Presentation\Http\Controllers\HealthFacilityController;
use App\Domains\Organization\Presentation\Http\Controllers\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::get('/health-facilities', HealthFacilityController::class);

Route::get('/organizations', [OrganizationController::class, 'index']);
Route::post('/organizations', [OrganizationController::class, 'store']);
Route::get('/organizations/{organization}', [OrganizationController::class, 'show']);
Route::patch('/organizations/{organization}', [OrganizationController::class, 'update']);
Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy']);
