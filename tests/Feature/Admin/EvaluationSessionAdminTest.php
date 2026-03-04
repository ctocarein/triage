<?php

namespace Tests\Feature\Admin;

use App\Domains\Evaluation\Models\EvaluationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsAdmin;
use Tests\TestCase;

class EvaluationSessionAdminTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsAdmin;

    public function test_index_returns_paginated_sessions(): void
    {
        EvaluationSession::factory()->count(3)->create();

        $this->actingAsAdmin()
            ->getJson('/api/admin/sessions')
            ->assertOk()
            ->assertJsonStructure(['data', 'total', 'per_page']);
    }

    public function test_index_filters_by_status(): void
    {
        EvaluationSession::factory()->create(['status' => 'in_progress']);
        EvaluationSession::factory()->completed()->create();

        $this->actingAsAdmin()
            ->getJson('/api/admin/sessions?status=in_progress')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_type(): void
    {
        EvaluationSession::factory()->create(['type' => 'complete']);
        EvaluationSession::factory()->create(['type' => 'thematic']);

        $this->actingAsAdmin()
            ->getJson('/api/admin/sessions?type=complete')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_show_returns_session_detail(): void
    {
        $session = EvaluationSession::factory()->create();

        $this->actingAsAdmin()
            ->getJson("/api/admin/sessions/{$session->public_id}")
            ->assertOk()
            ->assertJsonFragment(['public_id' => $session->public_id]);
    }

    public function test_cancel_cancels_in_progress_session(): void
    {
        $session = EvaluationSession::factory()->create(['status' => 'in_progress']);

        $this->actingAsAdmin()
            ->postJson("/api/admin/sessions/{$session->public_id}/cancel", [
                'reason' => 'Annulation administrative',
            ])
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelled']);

        $this->assertDatabaseHas('evaluation_sessions', [
            'id'     => $session->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cancel_rejected_if_session_not_in_progress(): void
    {
        $session = EvaluationSession::factory()->completed()->create();

        $this->actingAsAdmin()
            ->postJson("/api/admin/sessions/{$session->public_id}/cancel")
            ->assertStatus(409);
    }

    public function test_index_requires_admin_role(): void
    {
        $identity = \App\Domains\Identity\Infrastructure\Persistence\Models\Identity::factory()->create();

        $this->actingAs($identity, 'sanctum')
            ->getJson('/api/admin/sessions')
            ->assertStatus(403);
    }
}
