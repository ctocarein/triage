<?php

namespace Tests\Feature;

use App\Domains\Organization\Models\HealthDistrict;
use App\Domains\Organization\Models\HealthRegion;
use Database\Seeders\HealthMapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les coordonnees viennent d'un fichier, jamais d'une estimation : un district
 * place cent kilometres a cote met un marqueur sur la mauvaise ville et, des
 * qu'on en calcule des distances, envoie une femme vers la mauvaise structure.
 *
 * D'ou des controles qui refusent plutot que d'ecrire une valeur douteuse.
 */
class ImportHealthMapCoordinatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(HealthMapSeeder::class);
    }

    private function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'coords') . '.csv';
        file_put_contents($path, $contents);

        return $path;
    }

    public function test_it_loads_valid_coordinates(): void
    {
        $path = $this->csv(<<<CSV
        code,latitude,longitude
        DS-SAN-PEDRO,4.7485,-6.6363
        RS-SAN-PEDRO,4.7485,-6.6363
        CSV);

        $this->artisan('health-map:import-coordinates', ['file' => $path])
            ->assertSuccessful();

        $district = HealthDistrict::where('code', 'DS-SAN-PEDRO')->firstOrFail();

        $this->assertSame(4.7485, $district->latitude);
        $this->assertSame(-6.6363, $district->longitude);
        $this->assertNotNull(HealthRegion::where('code', 'RS-SAN-PEDRO')->first()->latitude);
    }

    public function test_it_refuses_swapped_latitude_and_longitude(): void
    {
        // L'erreur la plus courante des imports geographiques, et la plus
        // silencieuse : la Cote d'Ivoire a toutes ses latitudes positives et
        // toutes ses longitudes negatives, donc une paire inversee se lit
        // comme un point valide au large.
        $path = $this->csv("DS-ABENGOUROU,-3.4961,6.7297\n");

        $this->artisan('health-map:import-coordinates', ['file' => $path])
            ->assertFailed();

        $this->assertNull(HealthDistrict::where('code', 'DS-ABENGOUROU')->first()->latitude);
    }

    public function test_it_refuses_a_point_outside_the_country(): void
    {
        $path = $this->csv("DS-ABENGOUROU,48.8566,2.3522\n");

        $this->artisan('health-map:import-coordinates', ['file' => $path])
            ->assertFailed();

        $this->assertNull(HealthDistrict::where('code', 'DS-ABENGOUROU')->first()->latitude);
    }

    public function test_it_reports_an_unknown_code_instead_of_ignoring_it(): void
    {
        $path = $this->csv("DS-NEXISTEPAS,6.0,-5.0\n");

        $this->artisan('health-map:import-coordinates', ['file' => $path])
            ->expectsOutputToContain('code inconnu du referentiel')
            ->assertFailed();
    }

    public function test_dry_run_writes_nothing(): void
    {
        $path = $this->csv("DS-ABENGOUROU,6.7297,-3.4961\n");

        $this->artisan('health-map:import-coordinates', ['file' => $path, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertNull(HealthDistrict::where('code', 'DS-ABENGOUROU')->first()->latitude);
    }

    public function test_it_accepts_decimal_commas(): void
    {
        // Un export depuis un tableur en locale francaise ecrit « 6,7297 ».
        $path = $this->csv('DS-ABENGOUROU;x;y' . "\n" . "DS-ABENGOUROU,\"6,7297\",\"-3,4961\"\n");

        $this->artisan('health-map:import-coordinates', ['file' => $path])
            ->assertSuccessful();

        $this->assertSame(6.7297, HealthDistrict::where('code', 'DS-ABENGOUROU')->first()->latitude);
    }

    public function test_a_missing_file_fails_clearly(): void
    {
        $this->artisan('health-map:import-coordinates', ['file' => 'nowhere.csv'])
            ->expectsOutputToContain('Fichier introuvable')
            ->assertFailed();
    }
}
