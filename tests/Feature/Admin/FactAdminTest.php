<?php

namespace Tests\Feature\Admin;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\Fact;
use App\Domains\Evaluation\Models\FactMapping;
use App\Domains\Evaluation\Models\ModelVersion;
use App\Domains\Evaluation\Models\RiskSignal;
use App\Domains\Evaluation\Models\RiskModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsAdmin;
use Tests\TestCase;

class FactAdminTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsAdmin;

    public function test_index_returns_facts(): void
    {
        Fact::factory()->count(3)->create();

        $this->actingAsAdmin()->getJson('/api/admin/facts')
            ->assertOk()->assertJsonCount(3);
    }

    public function test_store_creates_fact(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/facts', ['key' => 'fact_new', 'label' => 'Nouveau fait'])
            ->assertCreated()
            ->assertJsonFragment(['key' => 'fact_new']);
    }

    public function test_toggle_flips_is_active(): void
    {
        $fact = Fact::factory()->create(['is_active' => true]);

        $this->actingAsAdmin()
            ->patchJson("/api/admin/facts/{$fact->id}/toggle")
            ->assertOk()
            ->assertJsonFragment(['is_active' => false]);
    }

    public function test_destroy_blocked_if_risk_signals_exist(): void
    {
        $model   = RiskModel::factory()->create();
        $fact    = Fact::factory()->create();
        $version = ModelVersion::factory()->forRiskModel()->active()->create();
        RiskSignal::create([
            'risk_model_id'    => $model->id,
            'model_version_id' => $version->id,
            'fact_key'         => $fact->key,
            'weight'           => 5,
            'is_active'        => true,
        ]);

        $this->actingAsAdmin()
            ->deleteJson("/api/admin/facts/{$fact->id}")
            ->assertStatus(409);
    }

    public function test_store_mapping_creates_mapping(): void
    {
        $fact     = Fact::factory()->create();
        $question = DynamicQuestionTemplate::factory()->create();

        $this->actingAsAdmin()
            ->postJson("/api/admin/facts/{$fact->id}/mappings", [
                'question_key' => $question->key,
                'answer_value' => ['oui'],
                'operator'     => '=',
            ])
            ->assertCreated()
            ->assertJsonFragment(['question_key' => $question->key]);
    }

    public function test_store_mapping_rejects_unknown_question_key(): void
    {
        $fact = Fact::factory()->create();

        $this->actingAsAdmin()
            ->postJson("/api/admin/facts/{$fact->id}/mappings", [
                'question_key' => 'q_inexistante',
                'answer_value' => ['oui'],
                'operator'     => '=',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['question_key']);
    }

    public function test_destroy_mapping(): void
    {
        $fact     = Fact::factory()->create();
        $question = DynamicQuestionTemplate::factory()->create();
        $mapping  = FactMapping::create([
            'fact_key'     => $fact->key,
            'question_key' => $question->key,
            'answer_value' => ['oui'],
            'operator'     => '=',
            'is_active'    => true,
        ]);

        $this->actingAsAdmin()
            ->deleteJson("/api/admin/facts/{$fact->id}/mappings/{$mapping->id}")
            ->assertOk();

        $this->assertDatabaseMissing('fact_mappings', ['id' => $mapping->id]);
    }
}
