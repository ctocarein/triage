<?php

namespace Tests\Feature;

use App\Domains\Evaluation\Services\Insight\GoldenPathRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentDeclinedGoldenPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_consent_declined_flow_matches_golden_path_fixture(): void
    {
        $this->seed();

        $fixture = json_decode(
            file_get_contents(base_path('database/fixtures/golden-paths/greeting-consent-no.json')),
            true
        );

        $this->assertIsArray($fixture);

        $result = app(GoldenPathRunner::class)->runFixture($fixture);

        $this->assertTrue($result['ok'] ?? false, $result['error'] ?? 'Golden path failed.');
    }
}
