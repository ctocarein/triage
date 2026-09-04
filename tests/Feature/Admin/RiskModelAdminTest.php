<?php

namespace Tests\Feature\Admin;

use App\Domains\Evaluation\Models\Fact;
use App\Domains\Evaluation\Models\ModelVersion;
use App\Domains\Evaluation\Models\RiskModel;
use App\Domains\Evaluation\Models\RiskSignal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsAdmin;
use Tests\TestCase;

class RiskModelAdminTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsAdmin;

    public function test_index_returns_models_with_counts(): void
    {
        RiskModel::factory()->count(2)->create();

        $this->actingAsAdmin()->getJson('/api/admin/risk-models')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonStructure([['id', 'key', 'signals_count', 'flags_count']]);
    }

    public function test_store_creates_model(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/risk-models', ['key' => 'risk_new', 'name' => 'Modele test'])
            ->assertCreated()
            ->assertJsonFragment(['key' => 'risk_new']);
    }

    public function test_toggle_flips_is_active(): void
    {
        $model = RiskModel::factory()->create(['is_active' => true]);

        $this->actingAsAdmin()
            ->patchJson("/api/admin/risk-models/{$model->id}/toggle")
            ->assertOk()
            ->assertJsonFragment(['is_active' => false]);
    }

    public function test_destroy_blocked_if_signals_exist(): void
    {
        $model   = RiskModel::factory()->create();
        $fact    = Fact::factory()->create();
        $version = ModelVersion::factory()->forRiskModel()->active()->create();
        RiskSignal::create([
            'risk_model_id'    => $model->id,
            'model_version_id' => $version->id,
            'fact_key'         => $fact->key,
            'weight'           => 10,
            'is_active'        => true,
        ]);

        $this->actingAsAdmin()
            ->deleteJson("/api/admin/risk-models/{$model->id}")
            ->assertStatus(409);
    }

    public function test_store_signal_requires_valid_fact_key(): void
    {
        $model = RiskModel::factory()->create();

        $this->actingAsAdmin()
            ->postJson("/api/admin/risk-models/{$model->id}/signals", [
                'fact_key' => 'nonexistent_fact',
                'weight'   => 10,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fact_key']);
    }

    public function test_store_signal_creates_signal(): void
    {
        $model = RiskModel::factory()->create();
        $fact  = Fact::factory()->create();
        ModelVersion::factory()->forRiskModel()->active()->create();

        $this->actingAsAdmin()
            ->postJson("/api/admin/risk-models/{$model->id}/signals", [
                'fact_key' => $fact->key,
                'weight'   => 20,
            ])
            ->assertCreated()
            ->assertJsonFragment(['weight' => 20]);
    }

    public function test_destroy_signal(): void
    {
        $model   = RiskModel::factory()->create();
        $fact    = Fact::factory()->create();
        $version = ModelVersion::factory()->forRiskModel()->active()->create();
        $signal  = RiskSignal::create([
            'risk_model_id'    => $model->id,
            'model_version_id' => $version->id,
            'fact_key'         => $fact->key,
            'weight'           => 5,
            'is_active'        => true,
        ]);

        $this->actingAsAdmin()
            ->deleteJson("/api/admin/risk-models/{$model->id}/signals/{$signal->id}")
            ->assertOk();

        $this->assertDatabaseMissing('risk_signals', ['id' => $signal->id]);
    }
}
