<?php

use App\Domains\Decision\Controllers\Patient\DecisionsController;
use App\Domains\Evaluation\Controllers\Patient\EvaluationAnswerController;
use App\Domains\Evaluation\Controllers\Patient\EvaluationsController;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Controllers\Patient\UploadsController;
use Illuminate\Support\Facades\Route;

Route::bind('session', function (string $value) {
    return EvaluationSession::where('public_id', $value)->firstOrFail();
});

Route::middleware(['auth:sanctum'])->prefix('evaluations')->group(function () {
    Route::post('/start', [EvaluationsController::class, 'start']);
    Route::get('/current', [EvaluationsController::class, 'current']);
    Route::post('/{session}/advance', [EvaluationsController::class, 'advance']);
    Route::get('/{session}/state', [EvaluationsController::class, 'state']);
    Route::post('/{session}/complete', [EvaluationsController::class, 'complete']);
});

Route::middleware(['auth:sanctum', 'security.gate'])->prefix('decisions')->group(function () {
    Route::post('/{session}/finalize', [DecisionsController::class, 'finalize']);
});

Route::middleware(['auth:sanctum'])->prefix('uploads')->group(function () {
    Route::post('/', [UploadsController::class, 'store']);
});

Route::middleware(['auth:sanctum'])->prefix('answers')->group(function () {
    Route::get('/session/{session}', [EvaluationAnswerController::class, 'index'])
        ->middleware('security.gate');
    Route::post('/', [EvaluationAnswerController::class, 'store']);
    Route::delete('/{id}', [EvaluationAnswerController::class, 'destroy']);
});
