<?php

namespace Tests\Unit;

use App\Domains\Decision\Models\DecisionInsight;
use App\Domains\Decision\Models\DecisionPath;
use App\Domains\Decision\Services\DecisionInsightService;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Services\DataQualityService;
use App\Domains\Evaluation\Services\RiskModelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class DecisionInsightServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Shadow mode désactivé par défaut pour isoler les tests qui ne déclarent
        // pas evaluateDirectWeights() dans leur mock.
        config(['vnext.shadow_mode' => false]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_flags_review_when_critical_missing(): void
    {
        config([
            'decision-thresholds.quality.critical_missing_requires_review' => true,
        ]);

        $session = $this->makeSession();

        $riskModel = Mockery::mock(RiskModelService::class);
        $riskModel->shouldReceive('evaluate')->andReturn([
            'risk_score' => 0.2,
            'risk_level' => 'low',
            'score_total' => 2.0,
            'max_score' => 10.0,
        ]);

        $qualityService = Mockery::mock(DataQualityService::class);
        $qualityService->shouldReceive('assess')->andReturn([
            'data_quality_score' => 0.9,
            'critical_missing' => true,
        ]);

        $service = new DecisionInsightService($riskModel, $qualityService);
        $insight = $service->generate($session);

        $this->assertInstanceOf(DecisionInsight::class, $insight);
        $this->assertTrue($insight->requires_human_validation);
        $this->assertSame('missing_critical_answers', $insight->human_validation_reason);
    }

    public function test_it_applies_confidence_threshold_and_suggested_action(): void
    {
        config([
            'decision-thresholds.confidence.min' => 0.8,
            'decision-thresholds.suggested_action.low' => 'follow_up',
        ]);

        $session = $this->makeSession();

        $riskModel = Mockery::mock(RiskModelService::class);
        $riskModel->shouldReceive('evaluate')->andReturn([
            'risk_score' => 0.4,
            'risk_level' => 'low',
            'score_total' => 4.0,
            'max_score' => 10.0,
        ]);

        $qualityService = Mockery::mock(DataQualityService::class);
        $qualityService->shouldReceive('assess')->andReturn([
            'data_quality_score' => 0.75,
            'critical_missing' => false,
        ]);

        $service = new DecisionInsightService($riskModel, $qualityService);
        $insight = $service->generate($session);

        $this->assertTrue($insight->requires_human_validation);
        $this->assertSame('confidence_below_threshold', $insight->human_validation_reason);
        $this->assertSame('follow_up', $insight->suggested_action);
    }

    public function test_it_uses_configured_confidence_interval_margin(): void
    {
        config([
            'decision-thresholds.confidence.min_margin' => 0.1,
            'decision-thresholds.confidence.margin_factor' => 0.3,
        ]);

        $session = $this->makeSession();

        $riskModel = Mockery::mock(RiskModelService::class);
        $riskModel->shouldReceive('evaluate')->andReturn([
            'risk_score' => 0.5,
            'risk_level' => 'low',
            'score_total' => 5.0,
            'max_score' => 10.0,
        ]);

        $qualityService = Mockery::mock(DataQualityService::class);
        $qualityService->shouldReceive('assess')->andReturn([
            'data_quality_score' => 0.9,
            'critical_missing' => false,
        ]);

        $service = new DecisionInsightService($riskModel, $qualityService);
        $insight = $service->generate($session);

        $this->assertSame(0.4, $insight->confidence_interval_low);
        $this->assertSame(0.6, $insight->confidence_interval_high);
    }

    // -------------------------------------------------------------------------
    // P1 — Shadow mode utilise le service injecté, pas app()
    // -------------------------------------------------------------------------

    public function test_shadow_mode_uses_injected_data_quality_service(): void
    {
        config(['vnext.shadow_mode' => true]);

        $session = $this->makeSession();

        $riskModel = Mockery::mock(RiskModelService::class);
        $riskModel->shouldReceive('evaluate')->andReturn([
            'risk_score' => 0.3, 'risk_level' => 'low', 'score_total' => 3.0, 'max_score' => 10.0,
        ]);
        $riskModel->shouldReceive('evaluateDirectWeights')->andReturn([
            'risk_score' => 0.25, 'risk_level' => 'low', 'score_total' => 2.5, 'max_score' => 10.0,
        ]);

        $qualityService = Mockery::mock(DataQualityService::class);
        // assess() doit être appelé DEUX fois : une pour la qualité principale, une pour le shadow
        $qualityService->shouldReceive('assess')->twice()->andReturn([
            'data_quality_score' => 0.8, 'critical_missing' => false,
        ]);

        $service = new DecisionInsightService($riskModel, $qualityService);
        $service->generate($session);

        // Mockery vérifiera que assess() a bien été appelé twice() lors du tearDown
        $this->expectNotToPerformAssertions();
    }

    // -------------------------------------------------------------------------
    // P3 — resolveDirection() correcte pour valeurs numériques
    // -------------------------------------------------------------------------

    public function test_direction_resolves_correctly_for_numeric_values(): void
    {
        $riskModel = Mockery::mock(RiskModelService::class);
        $qualityService = Mockery::mock(DataQualityService::class);
        $service = new DecisionInsightService($riskModel, $qualityService);

        $ref = new \ReflectionMethod($service, 'resolveDirection');

        $this->assertSame('increases_risk', $ref->invoke($service, 5));
        $this->assertSame('neutral', $ref->invoke($service, 0));
        $this->assertSame('decreases_risk', $ref->invoke($service, -3));
        $this->assertSame('increases_risk', $ref->invoke($service, true));
        $this->assertSame('decreases_risk', $ref->invoke($service, false));
        $this->assertSame('neutral', $ref->invoke($service, 'text'));
    }

    // -------------------------------------------------------------------------
    // P4 — Confiance = qualité des données pure, sans bonus pour high risk
    // -------------------------------------------------------------------------

    public function test_high_risk_confidence_equals_data_quality_score_without_bonus(): void
    {
        config([
            'decision-thresholds.confidence.min' => 0.5,
            'decision-thresholds.confidence.high_bonus' => 0.05,
        ]);

        $session = $this->makeSession();

        $riskModel = Mockery::mock(RiskModelService::class);
        $riskModel->shouldReceive('evaluate')->andReturn([
            'risk_score' => 0.9, 'risk_level' => 'high', 'score_total' => 25.0, 'max_score' => 30.0,
        ]);

        $qualityService = Mockery::mock(DataQualityService::class);
        $qualityService->shouldReceive('assess')->andReturn([
            'data_quality_score' => 0.65, 'critical_missing' => false,
        ]);

        $service = new DecisionInsightService($riskModel, $qualityService);
        $insight = $service->generate($session);

        // Sans bonus : confidence = 0.65 (et non 0.70)
        $this->assertSame(0.65, $insight->confidence_score);
        // L'IC doit être plus large (margin = max(0.05, 0.2 * 0.35) = 0.07)
        $this->assertSame(round(0.9 - 0.07, 4), $insight->confidence_interval_low);
        $this->assertSame(round(min(1.0, 0.9 + 0.07), 4), $insight->confidence_interval_high);
    }

    // -------------------------------------------------------------------------
    // P6 — zone_boundary_distance et ci_crosses_zone_boundary dans data_quality
    // -------------------------------------------------------------------------

    public function test_zone_boundary_info_populated_in_data_quality(): void
    {
        config([
            'risk-level.levels' => [
                ['name' => 'low',      'min' => 0,  'max' => 9],
                ['name' => 'moderate', 'min' => 10, 'max' => 19],
                ['name' => 'high',     'min' => 20, 'max' => PHP_INT_MAX],
            ],
            'decision-thresholds.confidence.min_margin' => 0.05,
            'decision-thresholds.confidence.margin_factor' => 0.2,
        ]);

        $session = $this->makeSession();

        $riskModel = Mockery::mock(RiskModelService::class);
        // score_total=19 → à 1 point du seuil high (20), ci_crosses=true si marge > 1
        $riskModel->shouldReceive('evaluate')->andReturn([
            'risk_score' => 0.95, 'risk_level' => 'moderate', 'score_total' => 19.0, 'max_score' => 20.0,
        ]);

        $qualityService = Mockery::mock(DataQualityService::class);
        $qualityService->shouldReceive('assess')->andReturn([
            'data_quality_score' => 0.8, 'critical_missing' => false,
        ]);

        $service = new DecisionInsightService($riskModel, $qualityService);
        $insight = $service->generate($session);

        $dataQuality = $insight->data_quality;
        $this->assertArrayHasKey('zone_boundary_distance', $dataQuality);
        $this->assertArrayHasKey('ci_crosses_zone_boundary', $dataQuality);
        $this->assertEquals(1.0, $dataQuality['zone_boundary_distance']); // |19 - 20| = 1 (float après JSON round-trip)
        // margin=max(0.05, 0.2*0.2)=0.05 → ciMarginScore=0.05*20=1.0 ≥ 1 → true
        $this->assertTrue($dataQuality['ci_crosses_zone_boundary']);
    }

    private function makeSession(): EvaluationSession
    {
        $identityId = (string) Str::uuid();

        DB::table('identities')->insert([
            'id' => $identityId,
            'kind' => 'person',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $path = DecisionPath::create([
            'key' => 'dp_test',
            'title' => 'Test Decision Path',
        ]);

        return EvaluationSession::create([
            'identity_id' => $identityId,
            'decision_path_id' => $path->id,
            'status' => 'in_progress',
            'type' => 'complete',
            'score' => 0,
        ]);
    }
}
