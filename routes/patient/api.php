<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Evaluation\Controllers\Patient\MobileBoardController;
use App\Domains\Recommendation\Controllers\Patient\CapsuleController;
use App\Domains\Report\Controllers\Patient\ReportController;
use App\Domains\Decision\Controllers\Patient\DecisionPathController;
use App\Domains\Evaluation\Controllers\Patient\ThematicBlocController;
use App\Domains\Notification\Controllers\Patient\ReminderController;
use App\Domains\Identity\Presentation\Http\Controllers\FieldAgentController;
use App\Domains\Identity\Presentation\Http\Controllers\BeneficiaryDirectoryController;
use App\Domains\Identity\Presentation\Http\Controllers\Patient\PatientQrController;

require base_path('app/Domains/Identity/Presentation/Http/Routes/identity.routes.php');
require base_path('app/Domains/Profile/Presentation/Http/Routes/profile.routes.php');
require base_path('app/Domains/Evaluation/Presentation/Http/Routes/evaluation.routes.php');

Route::group(['prefix' => 'mobile-board', 'middleware' => 'auth:sanctum'], function () {
    Route::get('/', MobileBoardController::class);
});


//this route is Thematic bloc route. It uses the 'sanctum' middleware to ensure that only authenticated users can access it.
Route::middleware(['auth:sanctum'])->prefix('thematic-blocs')->group(function () {
    Route::get('/', [ThematicBlocController::class, 'index']);
    Route::get('/{keyOrId}', [ThematicBlocController::class, 'show']);
});

//Reminders routes
Route::middleware('auth:sanctum')->prefix('reminders')->group(function () {
    Route::get('/', [ReminderController::class, 'index']);
    Route::post('/validate', [ReminderController::class, 'validateReminder']);
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

// route for getting capsules
Route::middleware(['auth:sanctum'])->prefix('capsules')->group(function () {
    Route::get('/{id}', [CapsuleController::class, 'show']);
});

// QR scan — bénéficiaire lie son compte à une organisation
Route::middleware('auth:sanctum')->prefix('qr')->group(function () {
    Route::post('/scan', [PatientQrController::class, 'scan']);
});

// Routes FieldAgent : création de bénéficiaires et évaluation assistée
Route::middleware(['auth:sanctum'])->prefix('agent')->group(function () {
    Route::get('/beneficiaries',  [BeneficiaryDirectoryController::class, 'index']);
    Route::post('/beneficiaries', [FieldAgentController::class, 'createBeneficiary']);
    Route::post('/qr-scan',       [FieldAgentController::class, 'scanQr']);
});
