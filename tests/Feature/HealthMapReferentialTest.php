<?php

namespace Tests\Feature;

use App\Domains\Organization\Models\HealthDistrict;
use App\Domains\Organization\Models\HealthRegion;
use Database\Seeders\HealthMapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le referentiel sanitaire est la cle d'orientation de la plateforme : un
 * district manquant ou mal rattache envoie une femme vers la mauvaise
 * structure. Ces tests verifient la source, pas des valeurs recopiees a la
 * main -- les totaux sont lus dans le fichier lui-meme.
 */
class HealthMapReferentialTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = json_decode(
            (string) file_get_contents(base_path(HealthMapSeeder::SOURCE)),
            true,
        );

        $this->seed(HealthMapSeeder::class);
    }

    public function test_every_region_and_district_of_the_source_is_seeded(): void
    {
        $expectedDistricts = array_sum(
            array_map(fn ($r) => count($r['districts']), $this->source['regions'])
        );

        $this->assertSame(count($this->source['regions']), HealthRegion::count());
        $this->assertSame($expectedDistricts, HealthDistrict::count());

        // Les totaux annonces par le document doivent correspondre a son
        // contenu : un ecart signalerait une source tronquee.
        $this->assertSame($this->source['totaux']['regions_sanitaires'], HealthRegion::count());
        $this->assertSame($this->source['totaux']['districts_sanitaires'], HealthDistrict::count());
    }

    public function test_every_district_hangs_off_a_region(): void
    {
        $this->assertSame(0, HealthDistrict::whereNull('health_region_id')->count());

        $district = HealthDistrict::where('code', 'DS-SAN-PEDRO')->firstOrFail();

        $this->assertSame('San-Pédro', $district->name);
        $this->assertSame('RS-SAN-PEDRO', $district->region->code);
        $this->assertSame('San-Pédro', $district->region->seat);
        $this->assertSame('San-Pédro', $district->region->pres);
    }

    public function test_no_district_claims_a_verified_region_assignment(): void
    {
        // Le document est explicite : le rattachement district -> region a ete
        // reconstitue et doit etre valide par la DIIS. Rien ne doit pretendre
        // le contraire avant cette validation.
        $this->assertSame(
            HealthDistrict::count(),
            HealthDistrict::where('region_assignment_verified', false)->count(),
        );
    }

    public function test_contested_assignments_keep_their_note(): void
    {
        $contested = HealthDistrict::whereNotNull('assignment_note')->pluck('name')->sort()->values();

        $this->assertEquals(['Kaniasso', 'Kouassi-Kouassikro', 'Nassian'], $contested->all());

        $nassian = HealthDistrict::where('code', 'DS-NASSIAN')->firstOrFail();

        $this->assertTrue($nassian->hasContestedAssignment());
        $this->assertStringContainsString('Gontougo', $nassian->assignment_note);
    }

    public function test_codes_and_slugs_are_unique_across_the_referential(): void
    {
        $this->assertSame(HealthDistrict::count(), HealthDistrict::distinct()->count('code'));
        $this->assertSame(HealthDistrict::count(), HealthDistrict::distinct()->count('slug'));
        $this->assertSame(HealthRegion::count(), HealthRegion::distinct()->count('code'));
        $this->assertSame(HealthRegion::count(), HealthRegion::distinct()->count('slug'));
    }

    public function test_seeding_twice_does_not_duplicate(): void
    {
        $regions   = HealthRegion::count();
        $districts = HealthDistrict::count();

        // Le referentiel est reedite : le seeder doit pouvoir etre rejoue sur
        // une base deja peuplee sans creer de doublons.
        $this->seed(HealthMapSeeder::class);

        $this->assertSame($regions, HealthRegion::count());
        $this->assertSame($districts, HealthDistrict::count());
    }

    public function test_coordinates_are_absent_and_expected_to_be(): void
    {
        // La source n'en porte aucune. Le test fige l'etat pour que le jour ou
        // elles arrivent, quelqu'un vienne ici le dire explicitement.
        $this->assertSame(0, HealthDistrict::whereNotNull('latitude')->count());
    }
}
