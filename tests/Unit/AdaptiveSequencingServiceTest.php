<?php

namespace Tests\Unit;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Services\AdaptiveSequencingService;
use App\Domains\Evaluation\Services\QuestionFlowService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class AdaptiveSequencingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * P5 — canChangeRiskLevel() doit inclure les questions conditionnellement masquées
     * dans son calcul de potentiel (mode conservatif).
     *
     * Scénario : un bloc contient une question à fort poids (15) masquée par un visible_if
     * non satisfait. En mode précis (visible only), potentiel = 0 → early exit déclenché.
     * En mode conservatif (toutes non répondues), potentiel = 15 → early exit bloqué.
     */
    public function test_can_change_risk_level_includes_hidden_questions(): void
    {
        config([
            'risk-level.levels' => [
                ['name' => 'low',      'min' => 0,  'max' => 9],
                ['name' => 'moderate', 'min' => 10, 'max' => 19],
                ['name' => 'high',     'min' => 20, 'max' => PHP_INT_MAX],
            ],
        ]);

        $service = new AdaptiveSequencingService();

        // Question à fort poids, non répondue, conditionnellement masquée
        $hiddenHighWeight = $this->makeQuestion(id: 1, blocKey: 'bloc_a', weight: 15, visible: false);

        $allTemplates = collect([$hiddenHighWeight]);
        $answeredIds  = [];
        $answers      = [];
        $profile      = [];

        $flow = Mockery::mock(QuestionFlowService::class);
        // shouldBeVisible retourne false → la question est masquée
        $flow->shouldReceive('shouldBeVisible')->andReturn(false);

        // Score actuel = 8 (zone low), seuil moderate = 10 → 2 points suffisent pour changer de zone.
        // Le potentiel conservatif = 15 → peut changer de zone → canChangeRiskLevel doit retourner true.
        $result = $service->canChangeRiskLevel(
            currentScore: 8.0,
            remainingBlocs: collect(['bloc_a']),
            allTemplates: $allTemplates,
            answeredIds: $answeredIds,
            answers: $answers,
            profile: $profile,
            flow: $flow,
        );

        $this->assertTrue($result, 'La question masquée à fort poids doit empêcher l\'early exit.');
    }

    public function test_can_change_risk_level_returns_false_when_no_potential(): void
    {
        config([
            'risk-level.levels' => [
                ['name' => 'low',      'min' => 0,  'max' => 9],
                ['name' => 'moderate', 'min' => 10, 'max' => 19],
                ['name' => 'high',     'min' => 20, 'max' => PHP_INT_MAX],
            ],
        ]);

        $service = new AdaptiveSequencingService();

        // Aucune question dans les blocs restants
        $flow = Mockery::mock(QuestionFlowService::class);

        $result = $service->canChangeRiskLevel(
            currentScore: 5.0,
            remainingBlocs: collect([]),
            allTemplates: collect([]),
            answeredIds: [],
            answers: [],
            profile: [],
            flow: $flow,
        );

        $this->assertFalse($result);
    }

    public function test_prioritize_blocs_uses_visible_questions_only(): void
    {
        config([
            'risk-level.levels' => [
                ['name' => 'low', 'min' => 0, 'max' => 9],
                ['name' => 'high', 'min' => 10, 'max' => PHP_INT_MAX],
            ],
        ]);

        $service = new AdaptiveSequencingService();

        $visibleLowWeight  = $this->makeQuestion(id: 1, blocKey: 'bloc_x', weight: 2, visible: true);
        $hiddenHighWeight  = $this->makeQuestion(id: 2, blocKey: 'bloc_y', weight: 20, visible: false);

        $allTemplates = collect([$visibleLowWeight, $hiddenHighWeight]);

        $flow = Mockery::mock(QuestionFlowService::class);
        $flow->shouldReceive('shouldBeVisible')
            ->andReturnUsing(fn ($q) => $q->id === 1);

        $sorted = $service->prioritizeBlocs(
            blocs: collect(['bloc_x', 'bloc_y']),
            allTemplates: $allTemplates,
            answeredIds: [],
            answers: [],
            profile: [],
            flow: $flow,
        );

        // En mode non-conservatif (prioritizeBlocs), bloc_y (poids caché) a potentiel 0
        // → bloc_x (poids visible=2) passe en premier
        $this->assertSame('bloc_x', $sorted->first());
    }

    private function makeQuestion(int $id, string $blocKey, float $weight, bool $visible): DynamicQuestionTemplate
    {
        $q = new DynamicQuestionTemplate();
        $q->id          = $id;
        $q->bloc_key    = $blocKey;
        $q->risk_weight = $weight;
        $q->visible_if  = $visible ? [] : ['source' => 'question', 'key' => 'never_answered', 'value' => true];

        return $q;
    }
}
