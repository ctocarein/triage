<?php

use App\Domains\Decision\Controllers\Patient\DecisionsController;
use App\Domains\Evaluation\Controllers\Patient\EvaluationsController;
use App\Domains\Evaluation\Controllers\Patient\UploadsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('evaluations')->group(function () {
    Route::post('/start', [EvaluationsController::class, 'start']);
    Route::post('/{session}/advance', [EvaluationsController::class, 'advance']);
    Route::get('/{session}/state', [EvaluationsController::class, 'state']);
    Route::post('/{session}/complete', [EvaluationsController::class, 'complete']);
});

Route::middleware(['auth:sanctum'])->prefix('decisions')->group(function () {
    Route::post('/{session}/finalize', [DecisionsController::class, 'finalize']);
});

Route::middleware(['auth:sanctum'])->prefix('uploads')->group(function () {
    Route::post('/', [UploadsController::class, 'store']);
});
