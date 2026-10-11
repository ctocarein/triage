<?php

namespace Tests\Feature;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Organization\Models\HealthFacility;
use Database\Seeders\HealthFacilitySampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les etablissements ou orienter une femme, avec ou sans mammographe.
 */
class HealthFacilityTest extends TestCase
{
    use RefreshDatabase;

    private function user(): Identity
    {
        return Identity::create(['kind' => 'person', 'status' => 'active']);
    }

    public function test_facilities_can_be_filtered_on_mammography(): void
    {
        HealthFacility::create(['name' => 'Avec', 'has_mammography' => true]);
        HealthFacility::create(['name' => 'Sans', 'has_mammography' => false]);

        $this->actingAs($this->user(), 'sanctum')
            ->getJson('/api/health-facilities?mammography=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Avec')
            ->assertJsonPath('data.0.has_mammography', true);

        $this->actingAs($this->user(), 'sanctum')
            ->getJson('/api/health-facilities?mammography=0')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Sans');

        $this->actingAs($this->user(), 'sanctum')
            ->getJson('/api/health-facilities')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_real_facilities_come_before_samples(): void
    {
        $this->seed(HealthFacilitySampleSeeder::class);
        HealthFacility::create(['name' => 'Zeta reel', 'has_mammography' => false]);

        $this->actingAs($this->user(), 'sanctum')
            ->getJson('/api/health-facilities')
            ->assertOk()
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('data.0.name', 'Zeta reel')
            ->assertJsonPath('data.0.is_sample', false)
            ->assertJsonPath('data.1.is_sample', true);
    }

    public function test_facilities_can_be_searched_by_name_or_municipality(): void
    {
        HealthFacility::create(['name' => 'Centre A', 'municipality' => 'Cocody']);
        HealthFacility::create(['name' => 'Centre B', 'municipality' => 'Abobo']);

        $this->actingAs($this->user(), 'sanctum')
            ->getJson('/api/health-facilities?search=cocody')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Centre A');
    }

    public function test_an_anonymous_visitor_is_refused(): void
    {
        $this->getJson('/api/health-facilities')->assertUnauthorized();
    }
}
