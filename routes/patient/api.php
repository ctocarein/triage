<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MobileBoardController;
use App\Http\Controllers\Account\LogoutController;
use App\Http\Controllers\Capsule\CapsuleController;
use App\Http\Controllers\Account\SetPinCodeController;
use App\Http\Controllers\Account\AnonymousAuthController;
use App\Http\Controllers\Account\MedicalProfileController;
use App\Http\Controllers\Account\PersonalProfileController;
use App\Http\Controllers\Account\RegisterOrLoginController;
use App\Http\Controllers\Evaluation\QuestionFlowController;
use App\Http\Controllers\Evaluation\EvaluationAnswerController;
use App\Http\Controllers\Evaluation\EvaluationSessionController;



Route::group(['prefix' => 'account'], function () {
    Route::post('/',RegisterOrLoginController::class);
    Route::post('/anonymous', AnonymousAuthController::class);
    Route::post('/pincode', SetPinCodeController::class);
    Route::post('/request-reset', [SetPinCodeController::class, 'requestReset']);
    Route::post('/confirm-reset', [SetPinCodeController::class, 'confirmReset']);

});

// This route is for logging out the user. It uses the 'sanctum' middleware to ensure that only authenticated users can access it.
// The LogoutController handles the logout process, which typically involves invalidating the user's token.
Route::group(['prefix' => 'account','middleware'=>'auth:sanctum'], function () {
    // Logout route
    Route::get('/logout',LogoutController::class);
    // Medical and Personal Profile routes
    // These routes are protected by the 'sanctum' middleware, ensuring that only authenticated users can access them.
    Route::get('/medical-profile', [MedicalProfileController::class, 'index']);
    Route::post('/medical-profile', [MedicalProfileController::class, 'store']);
    Route::put('/medical-profile', [MedicalProfileController::class, 'update']);
    Route::get('/personal-profile', [PersonalProfileController::class, 'index']);
    Route::post('/personal-profile', [PersonalProfileController::class, 'store']);
    Route::put('/personal-profile', [PersonalProfileController::class, 'update']);
});

Route::group(['prefix' => 'mobile-board','middleware'=>'auth:sanctum'], function () {
    Route::get('/', MobileBoardController::class);
});

// This route is for flow. It uses the 'sanctum' middleware to ensure that only authenticated users can access it.
Route::middleware(['auth:sanctum'])->prefix('flow')->group(function () {
    Route::get('/entry-points', [QuestionFlowController::class, 'entryPoints']);
    Route::post('/questions', [QuestionFlowController::class, 'flow']);
});

// This route is for the evaluation session. It uses the 'sanctum' middleware to ensure that only authenticated users can access it.
Route::middleware(['auth:sanctum'])->prefix('evaluations')->group(function () {
     Route::get('/', [EvaluationSessionController::class, 'index']);
    Route::post('/start', [EvaluationSessionController::class, 'start']);
    Route::post('/{session}/complete', [EvaluationSessionController::class, 'complete']);
    Route::get('/{session}', [EvaluationSessionController::class, 'show']);
});

// This route is for submitting answers to the evaluation. It uses the 'sanctum' middleware to ensure that only authenticated users can access it.

Route::prefix('answers')
    ->middleware(['auth:sanctum', 'has.active.session']) // Middleware optionnel
    ->group(function () {
        Route::get('/{sessionId}', [EvaluationAnswerController::class, 'index']);
        Route::post('/', [EvaluationAnswerController::class, 'store']);
        Route::delete('/{id}', [EvaluationAnswerController::class, 'destroy']);
    });


// route for getting capsules
Route::middleware(['auth:sanctum'])->prefix('capsules')->group(function () {
    Route::get('/{id}', [CapsuleController::class, 'show']);
});

// Report routes & Decision Path routes

Route::middleware('auth:sanctum')->prefix('reports')->group(function () {
    Route::get('/', [ReportController::class, 'index']);
    Route::get('/{id}', [ReportController::class, 'show']);
});

Route::middleware('auth:sanctum')->prefix('decision-paths')->group(function () {
    Route::get('/', [DecisionPathController::class, 'index']);
    Route::get('/{id}', [DecisionPathController::class, 'show']);
    Route::post('/', [DecisionPathController::class, 'store']);
    Route::put('/{id}', [DecisionPathController::class, 'update']);
    Route::delete('/{id}', [DecisionPathController::class, 'destroy']);
});
