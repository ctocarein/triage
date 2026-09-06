<?php

namespace Tests\Feature;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationAdvanceGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_advance_rejects_a_question_that_is_not_current(): void
    {
        $identity = Identity::create(['kind' => 'person', 'status' => 'active']);
        $currentQuestion = DynamicQuestionTemplate::create([
            'key' => 'current_question',
            'text' => 'Current question',
            'type' => 'boolean',
            'answer_target' => 'evaluation',
            'order' => 1,
            'bloc_key' => 'guard_bloc',
            'is_active' => true,
        ]);
        $nextQuestion = DynamicQuestionTemplate::create([
            'key' => 'next_question',
            'text' => 'Next question',
            'type' => 'boolean',
            'answer_target' => 'evaluation',
            'order' => 2,
            'bloc_key' => 'guard_bloc',
            'is_active' => true,
        ]);

        $session = EvaluationSession::create([
            'identity_id' => $identity->id,
            'current_question_id' => $currentQuestion->id,
            'current_bloc' => 'guard_bloc',
            'status' => 'in_progress',
            'type' => 'complete',
            'score' => 0,
            'started_at' => now(),
        ]);

        $this->actingAs($identity, 'sanctum')
            ->postJson("/api/evaluations/{$session->public_id}/advance", [
                'dynamic_question_template_id' => $nextQuestion->id,
                'value' => true,
            ])
            ->assertStatus(409);

        $this->assertDatabaseMissing('evaluation_answers', [
            'evaluation_session_id' => $session->id,
            'dynamic_question_template_id' => $nextQuestion->id,
        ]);
    }

    public function test_advance_accepts_the_current_question(): void
    {
        $identity = Identity::create(['kind' => 'person', 'status' => 'active']);
        $currentQuestion = DynamicQuestionTemplate::create([
            'key' => 'current_question',
            'text' => 'Current question',
            'type' => 'boolean',
            'answer_target' => 'evaluation',
            'order' => 1,
            'bloc_key' => 'guard_bloc',
            'is_active' => true,
        ]);
        $nextQuestion = DynamicQuestionTemplate::create([
            'key' => 'next_question',
            'text' => 'Next question',
            'type' => 'boolean',
            'answer_target' => 'evaluation',
            'order' => 2,
            'bloc_key' => 'guard_bloc',
            'is_active' => true,
        ]);

        $session = EvaluationSession::create([
            'identity_id' => $identity->id,
            'current_question_id' => $currentQuestion->id,
            'current_bloc' => 'guard_bloc',
            'status' => 'in_progress',
            'type' => 'complete',
            'score' => 0,
            'started_at' => now(),
        ]);

        $this->actingAs($identity, 'sanctum')
            ->postJson("/api/evaluations/{$session->public_id}/advance", [
                'dynamic_question_template_id' => $currentQuestion->id,
                'value' => true,
            ])
            ->assertOk()
            ->assertJsonPath('question.id', $nextQuestion->id);

        $this->assertDatabaseHas('evaluation_answers', [
            'evaluation_session_id' => $session->id,
            'dynamic_question_template_id' => $currentQuestion->id,
        ]);
    }
}
