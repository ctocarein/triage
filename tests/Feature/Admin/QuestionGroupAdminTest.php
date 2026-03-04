<?php

namespace Tests\Feature\Admin;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\QuestionGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsAdmin;
use Tests\TestCase;

class QuestionGroupAdminTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsAdmin;

    public function test_index_returns_groups(): void
    {
        QuestionGroup::factory()->count(2)->create();

        $this->actingAsAdmin()->getJson('/api/admin/question-groups')
            ->assertOk()->assertJsonCount(2);
    }

    public function test_store_creates_group(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/question-groups', ['key' => 'grp_test', 'label' => 'Groupe test'])
            ->assertCreated()
            ->assertJsonFragment(['key' => 'grp_test']);
    }

    public function test_store_rejects_duplicate_key(): void
    {
        QuestionGroup::factory()->create(['key' => 'grp_dup']);

        $this->actingAsAdmin()
            ->postJson('/api/admin/question-groups', ['key' => 'grp_dup', 'label' => 'X'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);
    }

    public function test_toggle_flips_is_active(): void
    {
        $group = QuestionGroup::factory()->create(['is_active' => true]);

        $this->actingAsAdmin()
            ->patchJson("/api/admin/question-groups/{$group->id}/toggle")
            ->assertOk()
            ->assertJsonFragment(['is_active' => false]);
    }

    public function test_destroy_blocked_if_questions_exist(): void
    {
        $group    = QuestionGroup::factory()->create();
        DynamicQuestionTemplate::factory()->create(['question_group_id' => $group->id]);

        $this->actingAsAdmin()
            ->deleteJson("/api/admin/question-groups/{$group->id}")
            ->assertStatus(409);
    }

    public function test_destroy_succeeds_if_empty(): void
    {
        $group = QuestionGroup::factory()->create();

        $this->actingAsAdmin()
            ->deleteJson("/api/admin/question-groups/{$group->id}")
            ->assertOk();

        $this->assertDatabaseMissing('question_groups', ['id' => $group->id]);
    }
}
