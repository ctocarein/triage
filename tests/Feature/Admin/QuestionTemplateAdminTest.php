<?php

namespace Tests\Feature\Admin;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\EvaluationAnswer;
use App\Domains\Evaluation\Models\EvaluationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsAdmin;
use Tests\TestCase;

class QuestionTemplateAdminTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsAdmin;

    // ── index ──────────────────────────────────────────────────────────────

    public function test_index_requires_auth(): void
    {
        $this->getJson('/api/admin/questions')->assertStatus(401);
    }

    public function test_index_requires_admin_role(): void
    {
        $identity = \App\Domains\Identity\Infrastructure\Persistence\Models\Identity::factory()->create();
        $this->actingAs($identity, 'sanctum')
            ->getJson('/api/admin/questions')
            ->assertStatus(403);
    }

    public function test_index_returns_paginated_questions(): void
    {
        DynamicQuestionTemplate::factory()->count(3)->create();

        $this->actingAsAdmin()
            ->getJson('/api/admin/questions')
            ->assertOk()
            ->assertJsonStructure(['data', 'total', 'per_page']);
    }

    public function test_index_filters_by_bloc_key(): void
    {
        DynamicQuestionTemplate::factory()->create(['bloc_key' => 'bloc_a']);
        DynamicQuestionTemplate::factory()->create(['bloc_key' => 'bloc_b']);

        $this->actingAsAdmin()
            ->getJson('/api/admin/questions?bloc_key=bloc_a')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_inactive_only(): void
    {
        DynamicQuestionTemplate::factory()->create(['is_active' => true]);
        DynamicQuestionTemplate::factory()->create(['is_active' => false]);

        $this->actingAsAdmin()
            ->getJson('/api/admin/questions?inactive_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ── show ───────────────────────────────────────────────────────────────

    public function test_show_returns_question(): void
    {
        $question = DynamicQuestionTemplate::factory()->create();

        $this->actingAsAdmin()
            ->getJson("/api/admin/questions/{$question->id}")
            ->assertOk()
            ->assertJsonFragment(['key' => $question->key]);
    }

    // ── store ──────────────────────────────────────────────────────────────

    public function test_store_creates_question(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/questions', [
                'key'      => 'q_test_store',
                'text'     => 'Question de test ?',
                'type'     => 'boolean',
                'is_active'=> true,
            ])
            ->assertCreated()
            ->assertJsonFragment(['key' => 'q_test_store']);

        $this->assertDatabaseHas('dynamic_question_templates', ['key' => 'q_test_store']);
    }

    public function test_store_rejects_duplicate_key(): void
    {
        DynamicQuestionTemplate::factory()->create(['key' => 'q_existing']);

        $this->actingAsAdmin()
            ->postJson('/api/admin/questions', [
                'key'  => 'q_existing',
                'text' => 'Autre texte',
                'type' => 'text',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);
    }

    public function test_store_rejects_invalid_type(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/questions', [
                'key'  => 'q_bad',
                'text' => 'Texte',
                'type' => 'unknown_type',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_store_rejects_duplicate_order_in_bloc(): void
    {
        DynamicQuestionTemplate::factory()->create(['bloc_key' => 'bloc_x', 'order' => 1]);

        $this->actingAsAdmin()
            ->postJson('/api/admin/questions', [
                'key'      => 'q_order_dup',
                'text'     => 'Texte',
                'type'     => 'text',
                'bloc_key' => 'bloc_x',
                'order'    => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order']);
    }

    // ── update ─────────────────────────────────────────────────────────────

    public function test_update_modifies_question(): void
    {
        $question = DynamicQuestionTemplate::factory()->create(['text' => 'Ancien texte']);

        $this->actingAsAdmin()
            ->putJson("/api/admin/questions/{$question->id}", [
                'key'  => $question->key,
                'text' => 'Nouveau texte',
                'type' => $question->type,
            ])
            ->assertOk()
            ->assertJsonFragment(['text' => 'Nouveau texte']);
    }

    // ── destroy ────────────────────────────────────────────────────────────

    public function test_destroy_deletes_question(): void
    {
        $question = DynamicQuestionTemplate::factory()->create();

        $this->actingAsAdmin()
            ->deleteJson("/api/admin/questions/{$question->id}")
            ->assertOk();

        $this->assertDatabaseMissing('dynamic_question_templates', ['id' => $question->id]);
    }

    public function test_destroy_blocked_if_answers_exist(): void
    {
        $session  = EvaluationSession::factory()->create();
        $question = DynamicQuestionTemplate::factory()->create();

        EvaluationAnswer::create([
            'evaluation_session_id'       => $session->id,
            'dynamic_question_template_id'=> $question->id,
            'value'                       => null,
            'is_skipped'                  => true,
            'status'                      => 'skipped',
            'order'                       => 1,
        ]);

        $this->actingAsAdmin()
            ->deleteJson("/api/admin/questions/{$question->id}")
            ->assertStatus(409);
    }

    // ── toggle ─────────────────────────────────────────────────────────────

    public function test_toggle_flips_is_active(): void
    {
        $question = DynamicQuestionTemplate::factory()->create(['is_active' => true]);

        $this->actingAsAdmin()
            ->patchJson("/api/admin/questions/{$question->id}/toggle")
            ->assertOk()
            ->assertJsonFragment(['is_active' => false]);

        $this->assertDatabaseHas('dynamic_question_templates', [
            'id'        => $question->id,
            'is_active' => false,
        ]);
    }

    // ── catalog/check ──────────────────────────────────────────────────────

    public function test_catalog_check_returns_errors_array(): void
    {
        $this->actingAsAdmin()
            ->getJson('/api/admin/questions/catalog/check')
            ->assertOk()
            ->assertJsonStructure(['errors']);
    }
}
