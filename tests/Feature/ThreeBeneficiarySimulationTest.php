<?php

namespace Tests\Feature;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThreeBeneficiarySimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_beneficiaries_can_start_and_capture_their_visit_intention(): void
    {
        $this->seed();

        foreach ([
            'beneficiary_sein' => 'sein',
            'beneficiary_gyneco' => 'cervical',
            'beneficiary_depistage' => 'depistage',
        ] as $label => $reason) {
            $identity = Identity::create([
                'kind' => 'person',
                'status' => 'active',
            ]);

            $start = $this->actingAs($identity, 'sanctum')
                ->postJson('/api/evaluations/start', [
                    'type' => 'complete',
                ]);

            $start->assertCreated();
            $start->assertJsonPath('question.key', 'greeting_welcome');

            $sessionId = (string) $start->json('session.public_id');

            $question = $this->advanceSkipped($identity, $sessionId, (int) $start->json('question.id'));
            $this->assertSame('greeting_confidential', $question['key'], "{$label} should reach greeting_confidential.");

            $question = $this->advanceSkipped($identity, $sessionId, (int) $question['id']);
            $this->assertSame('explicit_consent', $question['key'], "{$label} should reach explicit_consent.");

            $question = $this->advanceWithValue($identity, $sessionId, (int) $question['id'], 'yes');
            $this->assertSame('raison_visite', $question['key'], "{$label} should reach raison_visite.");

            $question = $this->advanceWithValue($identity, $sessionId, (int) $question['id'], [$reason]);
            $this->assertIsArray($question, "{$label} should receive a next question after raison_visite.");
            $this->assertNotSame('raison_visite', $question['key'] ?? null, "{$label} should move past raison_visite.");

            $state = $this->actingAs($identity, 'sanctum')
                ->getJson("/api/evaluations/{$sessionId}/state");

            $state->assertOk();
            $state->assertJsonPath('session.status', 'in_progress');
            $this->assertSame($question['key'] ?? null, $state->json('question.key'));

            $session = $identity->evaluationSessions()->latest('started_at')->firstOrFail();
            $raisonVisite = $session->answers()
                ->whereHas('question', fn ($query) => $query->where('key', 'raison_visite'))
                ->firstOrFail();

            $this->assertSame([$reason], $raisonVisite->value, "{$label} should persist raison_visite={$reason}.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function advanceSkipped(Identity $identity, string $sessionId, int $questionId): array
    {
        $response = $this->actingAs($identity, 'sanctum')
            ->postJson("/api/evaluations/{$sessionId}/advance", [
                'dynamic_question_template_id' => $questionId,
                'is_skipped' => true,
            ]);

        $response->assertOk();

        return $response->json('question');
    }

    /**
     * @return array<string, mixed>
     */
    private function advanceWithValue(Identity $identity, string $sessionId, int $questionId, mixed $value): array
    {
        $response = $this->actingAs($identity, 'sanctum')
            ->postJson("/api/evaluations/{$sessionId}/advance", [
                'dynamic_question_template_id' => $questionId,
                'value' => $value,
            ]);

        $response->assertOk();

        return $response->json('question');
    }
}
