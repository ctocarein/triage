<?php

namespace Tests\Feature\Admin;

use App\Domains\Evaluation\Models\ModelVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsAdmin;
use Tests\TestCase;

class ModelVersionAdminTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsAdmin;

    public function test_index_returns_versions(): void
    {
        ModelVersion::factory()->count(2)->forRiskModel()->create();

        $this->actingAsAdmin()
            ->getJson('/api/admin/model-versions')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_index_filters_by_name(): void
    {
        ModelVersion::factory()->forCatalog()->create();
        ModelVersion::factory()->forRiskModel()->create();

        $this->actingAsAdmin()
            ->getJson('/api/admin/model-versions?name=catalog')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_store_creates_inactive_version(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/model-versions', [
                'name'    => 'risk_model',
                'version' => '2.0.0',
            ])
            ->assertCreated()
            ->assertJsonFragment(['is_active' => false]);
    }

    public function test_store_rejects_invalid_name(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/model-versions', [
                'name'    => 'unknown_model',
                'version' => '1.0.0',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_rejects_invalid_version_format(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/model-versions', [
                'name'    => 'catalog',
                'version' => 'v1.0-beta',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['version']);
    }

    public function test_activate_sets_version_active_and_deactivates_others(): void
    {
        $active  = ModelVersion::factory()->forRiskModel()->active()->create();
        $pending = ModelVersion::factory()->forRiskModel()->create(['is_active' => false]);

        $this->actingAsAdmin()
            ->patchJson("/api/admin/model-versions/{$pending->id}/activate")
            ->assertOk()
            ->assertJsonFragment(['is_active' => true]);

        $this->assertDatabaseHas('model_versions', ['id' => $pending->id, 'is_active' => true]);
        $this->assertDatabaseHas('model_versions', ['id' => $active->id,  'is_active' => false]);
    }

    public function test_destroy_blocked_if_active(): void
    {
        $version = ModelVersion::factory()->active()->create();

        $this->actingAsAdmin()
            ->deleteJson("/api/admin/model-versions/{$version->id}")
            ->assertStatus(409);
    }

    public function test_destroy_succeeds_if_inactive(): void
    {
        $version = ModelVersion::factory()->create(['is_active' => false]);

        $this->actingAsAdmin()
            ->deleteJson("/api/admin/model-versions/{$version->id}")
            ->assertOk();

        $this->assertDatabaseMissing('model_versions', ['id' => $version->id]);
    }
}
