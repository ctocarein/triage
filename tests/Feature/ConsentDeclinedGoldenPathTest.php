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

    /*
     * test_consent_declined_flow_allows_a_16_year_old_beneficiary() a été retiré :
     * il vérifiait que la question `consent_declined_year` proposait bien l'année
     * de naissance d'une bénéficiaire de 16 ans. Cette question — ainsi que
     * `consent_declined_commune` — a été supprimée du catalogue : le parcours de
     * refus de consentement ne collecte plus que le WhatsApp facultatif.
     *
     * Le garde-fou « une mineure est bien orientée vers le parcours de refus et
     * peut le mener jusqu'à la terminaison » reste couvert par
     * FourBeneficiarySimulationTest::test_b1_minor_age_17_is_blocked_via_birth_date_to_consent_declined().
     */
}
