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
