<?php

namespace Tests\Unit;

use App\Domains\Decision\Models\DecisionPath;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Services\FactsEngine;
use App\Domains\Evaluation\Services\RiskEngine;
use App\Domains\Evaluation\Services\RiskModelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class RiskModelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * P2 — Le fallback max_score doit utiliser le catalogue actif complet,
     * pas les réponses de la session en cours.
     *
     * Une session incomplète (early exit, abandon) ne doit pas produire
     * un max_score sous-estimé qui gonflerait artificiellement le risk_score.
     */
    public function test_max_score_fallback_uses_active_catalog_not_session_answers(): void
    {
        // Catalogue : 3 questions actives dont 2 ont du poids
        DynamicQuestionTemplate::create([
            'key' => 'q_a', 'text' => 'Q A', 'type' => 'boolean', 'risk_weight' => 10, 'is_active' => true,
        ]);
        DynamicQuestionTemplate::create([
            'key' => 'q_b', 'text' => 'Q B', 'type' => 'boolean', 'risk_weight' => 20, 'is_active' => true,
        ]);
        DynamicQuestionTemplate::create([
            'key' => 'q_c', 'text' => 'Q C', 'type' => 'boolean', 'risk_weight' => 0, 'is_active' => true,
        ]);
        // Question inactive : ne doit pas compter
        DynamicQuestionTemplate::create([
            'key' => 'q_inactive', 'text' => 'Q Inactive', 'type' => 'boolean', 'risk_weight' => 50, 'is_active' => false,
        ]);

        // Session sans decisionPath et sans réponses (simulant un early exit avant toute réponse)
        $session = $this->makeSession();

        // Les deux moteurs ne trouvent aucun signal configuré → max_score = 0 → fallback
        $factsEngine = Mockery::mock(FactsEngine::class);
        $factsEngine->shouldReceive('evaluate')->andReturn([]);

        $riskEngine = Mockery::mock(RiskEngine::class);
        $riskEngine->shouldReceive('evaluate')->andReturn([
            'risk_score' => 0.0, 'risk_level' => 'low', 'score_total' => 0.0, 'max_score' => 0.0,
        ]);

        $service = new RiskModelService($factsEngine, $riskEngine);
        $result = $service->evaluateDirectWeights($session);

        // max_score doit être la somme du catalogue actif (10+20=30), pas 0 ou somme des réponses (0)
        $this->assertSame(30.0, $result['max_score']);
    }

    public function test_max_score_ignores_inactive_questions(): void
    {
        DynamicQuestionTemplate::create([
            'key' => 'q_active',   'text' => 'Active',   'type' => 'boolean', 'risk_weight' => 15, 'is_active' => true,
        ]);
        DynamicQuestionTemplate::create([
            'key' => 'q_inactive', 'text' => 'Inactive', 'type' => 'boolean', 'risk_weight' => 100, 'is_active' => false,
        ]);

        $session = $this->makeSession();

        $factsEngine = Mockery::mock(FactsEngine::class);
        $factsEngine->shouldReceive('evaluate')->andReturn([]);

        $riskEngine = Mockery::mock(RiskEngine::class);
        $riskEngine->shouldReceive('evaluate')->andReturn([
            'risk_score' => 0.0, 'risk_level' => 'low', 'score_total' => 0.0, 'max_score' => 0.0,
        ]);

        $service = new RiskModelService($factsEngine, $riskEngine);
        $result = $service->evaluateDirectWeights($session);

        $this->assertSame(15.0, $result['max_score']);
    }

    private function makeSession(): EvaluationSession
    {
        $identityId = (string) Str::uuid();

        DB::table('identities')->insert([
            'id'         => $identityId,
            'kind'       => 'person',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $path = DecisionPath::create([
            'key'   => 'dp_riskmodel_test_' . uniqid(),
            'title' => 'Risk Model Test Path',
        ]);

        return EvaluationSession::create([
            'identity_id'      => $identityId,
            'decision_path_id' => $path->id,
            'status'           => 'in_progress',
            'type'             => 'complete',
            'score'            => 0,
        ]);
    }
}
