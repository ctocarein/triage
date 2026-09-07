<?php

namespace Tests\Feature;

use App\Domains\Access\Models\Membership;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Organization\Models\HealthDistrict;
use App\Domains\Organization\Models\Organization;
use Database\Seeders\HealthMapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La carte nationale du back office.
 *
 * Deux principes s'y affrontent et se resolvent de la meme facon : montrer la
 * couverture reelle -- donc tout le pays, y compris ce qui n'est pas couvert --
 * sans exposer des femmes identifiables dans les districts a faible effectif.
 */
class HealthMapStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(HealthMapSeeder::class);
        config()->set('health-map.min_disclosure_count', 5);
    }

    private function admin(): Identity
    {
        $identity     = Identity::create(['kind' => 'person', 'status' => 'active']);
        $organization = Organization::create(['type' => 'clinic', 'name' => 'Siege', 'status' => 'active']);

        Membership::create([
            'identity_id'     => $identity->id,
            'organization_id' => $organization->id,
            'role'            => 'PlatformAdmin',
            'status'          => 'active',
        ]);

        return $identity;
    }

    private function district(string $code): HealthDistrict
    {
        return HealthDistrict::where('code', $code)->firstOrFail();
    }

    /** Cree une femme rattachee au district, avec une session terminee. */
    private function evaluatedWoman(
        string $districtCode,
        string $riskLevel = 'low',
        bool $urgent = false,
    ): Identity {
        $identity = Identity::create(['kind' => 'person', 'status' => 'active']);

        $identity->personalProfile()->create([
            'health_district_id' => $this->district($districtCode)->id,
        ]);

        EvaluationSession::create([
            'identity_id'  => $identity->id,
            'status'       => 'completed',
            'type'         => 'complete',
            'score'        => 0,
            'risk_level'   => $riskLevel,
            'started_at'   => now(),
            'completed_at' => now(),
            'context'      => $urgent
                ? ['urgent_referral' => ['reason' => 'pelvic_mass', 'raised_at' => now()->toISOString()]]
                : [],
        ]);

        return $identity;
    }

    /** @return array<string, mixed> */
    private function fetch(): array
    {
        return $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/health-map/stats')
            ->assertOk()
            ->json();
    }

    /** @return array<string, mixed>|null */
    private function districtRow(array $payload, string $code): ?array
    {
        foreach ($payload['health_districts'] as $row) {
            if ($row['code'] === $code) {
                return $row;
            }
        }

        return null;
    }

    // -----------------------------------------------------------------------

    public function test_the_whole_country_is_returned_not_only_what_is_covered(): void
    {
        // Une carte qui n'affiche que les districts couverts se lit comme une
        // couverture totale.
        $payload = $this->fetch();

        $this->assertCount(HealthDistrict::count(), $payload['health_districts']);
        $this->assertSame(HealthDistrict::count(), $payload['coverage']['districts_total']);
        $this->assertSame(0, $payload['coverage']['districts_covered']);
    }

    public function test_figures_are_disclosed_above_the_floor(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->evaluatedWoman('DS-SAN-PEDRO', $i < 2 ? 'high' : 'low', $i === 0);
        }

        $row = $this->districtRow($this->fetch(), 'DS-SAN-PEDRO');

        $this->assertTrue($row['disclosed']);
        $this->assertTrue($row['covered']);
        $this->assertSame(5, $row['women_reached']);
        $this->assertSame(5, $row['evaluations_completed']);
        $this->assertSame(2, $row['high_risk']);
        $this->assertSame(1, $row['urgent_referrals']);
    }

    public function test_figures_are_withheld_below_the_floor_but_the_district_stays_covered(): void
    {
        // Trois femmes dans un district : « 3 femmes, dont 1 orientation
        // urgente » designerait quelqu'un pour qui connait le terrain.
        for ($i = 0; $i < 3; $i++) {
            $this->evaluatedWoman('DS-TABOU', 'low', $i === 0);
        }

        $row = $this->districtRow($this->fetch(), 'DS-TABOU');

        $this->assertFalse($row['disclosed']);
        $this->assertNull($row['women_reached']);
        $this->assertNull($row['urgent_referrals']);

        // Mais il reste sur la carte et reste couvert : le masquer ferait
        // croire a une absence de couverture.
        $this->assertTrue($row['covered']);
    }

    public function test_a_district_with_an_evaluator_but_no_evaluation_is_covered(): void
    {
        $organization = Organization::create([
            'type'               => 'clinic',
            'name'               => 'CHR San-Pédro',
            'status'             => 'active',
            'health_district_id' => $this->district('DS-SAN-PEDRO')->id,
        ]);

        $ambassador = Identity::create(['kind' => 'person', 'status' => 'active']);
        Membership::create([
            'identity_id'     => $ambassador->id,
            'organization_id' => $organization->id,
            'role'            => 'Ambassador',
            'status'          => 'active',
        ]);

        $row = $this->districtRow($this->fetch(), 'DS-SAN-PEDRO');

        // La capacite compte, meme sans activite : voir le district vide
        // ferait envoyer des moyens la ou il y en a deja.
        $this->assertSame(1, $row['evaluators']);
        $this->assertTrue($row['covered']);
        $this->assertFalse($row['disclosed']);
    }

    public function test_activity_is_attached_to_the_woman_district_not_the_evaluator_one(): void
    {
        // Une ambassadrice de San-Pedro evalue une femme de Tabou : la carte
        // doit dire ou vit la femme.
        $organization = Organization::create([
            'type'               => 'clinic',
            'name'               => 'CHR San-Pédro',
            'status'             => 'active',
            'health_district_id' => $this->district('DS-SAN-PEDRO')->id,
        ]);

        $ambassador = Identity::create(['kind' => 'person', 'status' => 'active']);
        Membership::create([
            'identity_id'     => $ambassador->id,
            'organization_id' => $organization->id,
            'role'            => 'Ambassador',
            'status'          => 'active',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->evaluatedWoman('DS-TABOU');
        }

        $payload = $this->fetch();

        $this->assertSame(5, $this->districtRow($payload, 'DS-TABOU')['women_reached']);
        $this->assertNull($this->districtRow($payload, 'DS-SAN-PEDRO')['women_reached']);
    }

    public function test_each_district_says_whether_its_region_is_verified(): void
    {
        // Agreger par region reste indicatif tant que la DIIS n'a pas valide
        // l'affectation : celui qui regarde la carte doit pouvoir le savoir.
        $row = $this->districtRow($this->fetch(), 'DS-SAN-PEDRO');

        $this->assertFalse($row['region_assignment_verified']);
        $this->assertSame('RS-SAN-PEDRO', $row['region']['code']);
    }

    public function test_the_payload_says_how_many_districts_can_be_placed(): void
    {
        $payload = $this->fetch();

        // Aucune coordonnee dans la carte sanitaire : le client doit pouvoir
        // avertir plutot que d'afficher une carte a moitie vide sans explication.
        $this->assertSame(0, $payload['coverage']['districts_with_coordinates']);
        $this->assertSame(5, $payload['min_disclosure_count']);
    }

    public function test_a_beneficiary_cannot_read_the_map(): void
    {
        $identity     = Identity::create(['kind' => 'person', 'status' => 'active']);
        $organization = Organization::create(['type' => 'clinic', 'name' => 'Org', 'status' => 'active']);

        Membership::create([
            'identity_id'     => $identity->id,
            'organization_id' => $organization->id,
            'role'            => 'Beneficiary',
            'status'          => 'active',
        ]);

        $this->actingAs($identity, 'sanctum')
            ->getJson('/api/admin/health-map/stats')
            ->assertForbidden();
    }
}
