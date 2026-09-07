<?php

use App\Domains\Evaluation\Controllers\Admin\CatalogController;
use App\Domains\Evaluation\Controllers\Admin\EvaluationSessionAdminController;
use App\Domains\Evaluation\Controllers\Admin\FactController;
use App\Domains\Evaluation\Controllers\Admin\ModelVersionController;
use App\Domains\Evaluation\Controllers\Admin\QuestionGroupController;
use App\Domains\Evaluation\Controllers\Admin\QuestionTemplateController;
use App\Domains\Evaluation\Controllers\Admin\RiskModelController;
use App\Domains\Evaluation\Controllers\Admin\ThematicBlocController;
use App\Domains\Evaluation\Controllers\Admin\HealthMapStatsController;
use App\Domains\Evaluation\Controllers\Admin\TriageStatsController;
use App\Domains\Decision\Controllers\Admin\DecisionInsightAdminController;
use App\Domains\Identity\Presentation\Http\Controllers\Admin\OrgManagerOnboardingAdminController;
use App\Domains\Identity\Presentation\Http\Controllers\Admin\PatientAdminController;
use App\Domains\Identity\Presentation\Http\Controllers\Admin\UserAdminController;
use App\Domains\Identity\Presentation\Http\Controllers\Admin\UserProvisioningController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:PlatformAdmin,OrgManager'])
    ->prefix('admin')
    ->group(function () {

        // ----------------------------------------------------------------
        // Catalogue — vue d'ensemble + cohérence
        // ----------------------------------------------------------------
        Route::prefix('catalog')->group(function () {
            Route::get('/summary', [CatalogController::class, 'summary']);
            Route::get('/check',   [CatalogController::class, 'check']);
        });

        // ----------------------------------------------------------------
        // Questions
        // ----------------------------------------------------------------
        Route::prefix('questions')->group(function () {
            Route::get('/',                           [QuestionTemplateController::class, 'index']);
            Route::post('/',                          [QuestionTemplateController::class, 'store']);
            Route::patch('/bulk-toggle',              [QuestionTemplateController::class, 'bulkToggle']);
            Route::patch('/reorder',                  [QuestionTemplateController::class, 'reorder']);
            Route::get('/{question}',                 [QuestionTemplateController::class, 'show']);
            Route::put('/{question}',                 [QuestionTemplateController::class, 'update']);
            Route::delete('/{question}',              [QuestionTemplateController::class, 'destroy']);
            Route::patch('/{question}/toggle',        [QuestionTemplateController::class, 'toggle']);
            Route::get('/{question}/references',      [QuestionTemplateController::class, 'references']);
        });

        // ----------------------------------------------------------------
        // Groupes de questions
        // ----------------------------------------------------------------
        Route::prefix('question-groups')->group(function () {
            Route::get('/',                [QuestionGroupController::class, 'index']);
            Route::post('/',               [QuestionGroupController::class, 'store']);
            Route::get('/{group}',         [QuestionGroupController::class, 'show']);
            Route::put('/{group}',         [QuestionGroupController::class, 'update']);
            Route::delete('/{group}',      [QuestionGroupController::class, 'destroy']);
            Route::patch('/{group}/toggle',[QuestionGroupController::class, 'toggle']);
        });

        // ----------------------------------------------------------------
        // Blocs thématiques
        // ----------------------------------------------------------------
        Route::prefix('thematic-blocs')->group(function () {
            Route::get('/',                        [ThematicBlocController::class, 'index']);
            Route::post('/',                       [ThematicBlocController::class, 'store']);
            Route::patch('/reorder',               [ThematicBlocController::class, 'reorder']);
            Route::get('/{bloc}',                  [ThematicBlocController::class, 'show']);
            Route::put('/{bloc}',                  [ThematicBlocController::class, 'update']);
            Route::delete('/{bloc}',               [ThematicBlocController::class, 'destroy']);
            Route::patch('/{bloc}/toggle',         [ThematicBlocController::class, 'toggle']);
            Route::get('/{bloc}/questions',        [ThematicBlocController::class, 'questions']);
        });

        // ----------------------------------------------------------------
        // Modèles de risque
        // ----------------------------------------------------------------
        Route::prefix('risk-models')->group(function () {
            Route::get('/',                      [RiskModelController::class, 'index']);
            Route::post('/',                     [RiskModelController::class, 'store']);
            Route::get('/{risk_model}',          [RiskModelController::class, 'show']);
            Route::put('/{risk_model}',          [RiskModelController::class, 'update']);
            Route::delete('/{risk_model}',       [RiskModelController::class, 'destroy']);
            Route::patch('/{risk_model}/toggle', [RiskModelController::class, 'toggle']);

            Route::get('/{risk_model}/signals',                 [RiskModelController::class, 'signals']);
            Route::post('/{risk_model}/signals',                [RiskModelController::class, 'storeSignal']);
            Route::put('/{risk_model}/signals/{signal}',        [RiskModelController::class, 'updateSignal']);
            Route::delete('/{risk_model}/signals/{signal}',     [RiskModelController::class, 'destroySignal']);

            Route::get('/{risk_model}/flags',                   [RiskModelController::class, 'flags']);
            Route::post('/{risk_model}/flags',                  [RiskModelController::class, 'storeFlag']);
            Route::put('/{risk_model}/flags/{flag}',            [RiskModelController::class, 'updateFlag']);
            Route::delete('/{risk_model}/flags/{flag}',         [RiskModelController::class, 'destroyFlag']);
        });

        // ----------------------------------------------------------------
        // Faits
        // ----------------------------------------------------------------
        Route::prefix('facts')->group(function () {
            Route::get('/',                          [FactController::class, 'index']);
            Route::post('/',                         [FactController::class, 'store']);
            Route::get('/{fact}',                    [FactController::class, 'show']);
            Route::put('/{fact}',                    [FactController::class, 'update']);
            Route::delete('/{fact}',                 [FactController::class, 'destroy']);
            Route::patch('/{fact}/toggle',           [FactController::class, 'toggle']);

            Route::get('/{fact}/mappings',           [FactController::class, 'mappings']);
            Route::post('/{fact}/mappings',          [FactController::class, 'storeMapping']);
            Route::put('/{fact}/mappings/{mapping}', [FactController::class, 'updateMapping']);
            Route::delete('/{fact}/mappings/{mapping}', [FactController::class, 'destroyMapping']);
        });

        // ----------------------------------------------------------------
        // Versions de modèles
        // ----------------------------------------------------------------
        Route::prefix('model-versions')->group(function () {
            Route::get('/',                           [ModelVersionController::class, 'index']);
            Route::post('/',                          [ModelVersionController::class, 'store']);
            Route::get('/{model_version}',            [ModelVersionController::class, 'show']);
            Route::put('/{model_version}',            [ModelVersionController::class, 'update']);
            Route::delete('/{model_version}',         [ModelVersionController::class, 'destroy']);
            Route::patch('/{model_version}/activate', [ModelVersionController::class, 'activate']);
        });

        // ----------------------------------------------------------------
        // Sessions (supervision)
        // ----------------------------------------------------------------
        Route::prefix('sessions')->group(function () {
            Route::get('/',              [EvaluationSessionAdminController::class, 'index']);
            Route::get('/{session}',     [EvaluationSessionAdminController::class, 'show']);
            Route::post('/{session}/cancel', [EvaluationSessionAdminController::class, 'cancel']);
        });

        // ----------------------------------------------------------------
        // Onboarding OrgManager
        // ----------------------------------------------------------------
        Route::prefix('onboarding')->group(function () {
            Route::get('/',                              [OrgManagerOnboardingAdminController::class, 'index']);
            Route::patch('/{onboardingRequest}/approve', [OrgManagerOnboardingAdminController::class, 'approve']);
            Route::patch('/{onboardingRequest}/reject',  [OrgManagerOnboardingAdminController::class, 'reject']);
        });

        // ----------------------------------------------------------------
        // Patients + Utilisateurs
        // ----------------------------------------------------------------
        Route::get('/patients', [PatientAdminController::class, 'index']);

        Route::get('/users',                          [UserAdminController::class, 'index']);
        Route::post('/users/practitioner',            [UserProvisioningController::class, 'createPractitioner']);
        Route::post('/users/field-agent',             [UserProvisioningController::class, 'createFieldAgent']);
        Route::patch('/users/change-password',        [UserProvisioningController::class, 'changePassword']);

        // ----------------------------------------------------------------
        // Stats + Insights
        // ----------------------------------------------------------------
        Route::get('/triage/stats', [TriageStatsController::class, 'index']);

        // Carte nationale : tous les districts, couverts ou non.
        Route::get('/health-map/stats', HealthMapStatsController::class);

        Route::prefix('decisions')->group(function () {
            Route::get('/',      [DecisionInsightAdminController::class, 'index']);
            Route::get('/stats', [DecisionInsightAdminController::class, 'stats']);
        });

    });
