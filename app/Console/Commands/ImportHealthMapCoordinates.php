<?php

namespace App\Console\Commands;

use App\Domains\Organization\Models\HealthDistrict;
use App\Domains\Organization\Models\HealthRegion;
use Illuminate\Console\Command;

/**
 * Loads coordinates onto the health map from a CSV.
 *
 * The national health map carries none, and they cannot be guessed: a district
 * placed a hundred kilometres off puts a marker on the wrong town and, once
 * distances are computed from it, sends a woman to the wrong facility. So they
 * arrive from a file, and this command is the door.
 *
 * The bounding-box check is the point of the exercise. Swapped latitude and
 * longitude is the most common error in geographic imports and it produces
 * coordinates that look perfectly valid -- Cote d'Ivoire straddles the
 * meridian, so a swap lands in the Atlantic rather than throwing.
 *
 *   php artisan health-map:import-coordinates coords.csv
 *   php artisan health-map:import-coordinates coords.csv --dry-run
 *
 * CSV: code,latitude,longitude -- header optional, codes are DS-* or RS-*.
 */
class ImportHealthMapCoordinates extends Command
{
    protected $signature = 'health-map:import-coordinates
                            {file : Chemin du CSV (code,latitude,longitude)}
                            {--dry-run : Analyse le fichier sans rien ecrire}';

    protected $description = "Charge les coordonnees des districts et regions sanitaires depuis un CSV";

    /**
     * Cote d'Ivoire, generously bounded. Anything outside is an error in the
     * file, not a district in an unexpected place.
     */
    private const LAT_MIN = 4.0;
    private const LAT_MAX = 11.0;
    private const LNG_MIN = -9.0;
    private const LNG_MAX = -2.0;

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("Fichier introuvable : {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error("Fichier illisible : {$path}");

            return self::FAILURE;
        }

        $applied  = 0;
        $rejected = [];
        $line     = 0;

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $line++;

            if ($row === [null] || count($row) < 3) {
                continue;
            }

            $code = trim((string) $row[0]);

            // Header row, or a stray line.
            if ($code === '' || ! preg_match('/^(DS|RS)-/i', $code)) {
                continue;
            }

            $latitude  = str_replace(',', '.', trim((string) $row[1]));
            $longitude = str_replace(',', '.', trim((string) $row[2]));

            if (! is_numeric($latitude) || ! is_numeric($longitude)) {
                $rejected[] = "ligne {$line} ({$code}) : coordonnees non numeriques";
                continue;
            }

            $latitude  = (float) $latitude;
            $longitude = (float) $longitude;

            if ($this->looksSwapped($latitude, $longitude)) {
                $rejected[] = "ligne {$line} ({$code}) : latitude et longitude semblent inversees";
                continue;
            }

            if (! $this->withinCountry($latitude, $longitude)) {
                $rejected[] = "ligne {$line} ({$code}) : hors des limites de la Cote d'Ivoire";
                continue;
            }

            $model = str_starts_with(strtoupper($code), 'DS-')
                ? HealthDistrict::where('code', $code)->first()
                : HealthRegion::where('code', $code)->first();

            if (! $model) {
                $rejected[] = "ligne {$line} : code inconnu du referentiel ({$code})";
                continue;
            }

            if (! $dryRun) {
                $model->update(['latitude' => $latitude, 'longitude' => $longitude]);
            }

            $applied++;
        }

        fclose($handle);

        return $this->report($applied, $rejected, $dryRun);
    }

    /**
     * A swap is silent otherwise: Cote d'Ivoire's latitudes are all positive
     * and its longitudes all negative, so an inverted pair reads as a valid
     * point off the coast.
     */
    private function looksSwapped(float $latitude, float $longitude): bool
    {
        return $latitude < 0 && $longitude > 0;
    }

    private function withinCountry(float $latitude, float $longitude): bool
    {
        return $latitude >= self::LAT_MIN && $latitude <= self::LAT_MAX
            && $longitude >= self::LNG_MIN && $longitude <= self::LNG_MAX;
    }

    /**
     * @param string[] $rejected
     */
    private function report(int $applied, array $rejected, bool $dryRun): int
    {
        $this->newLine();
        $this->line($dryRun
            ? "<comment>Simulation</comment> — aucune ecriture"
            : "<info>Import termine</info>");

        $this->line("  appliquees : {$applied}");
        $this->line('  rejetees   : ' . count($rejected));

        foreach ($rejected as $reason) {
            $this->warn("    - {$reason}");
        }

        $missingDistricts = HealthDistrict::whereNull('latitude')->count();
        $missingRegions   = HealthRegion::whereNull('latitude')->count();

        $this->newLine();
        $this->line('Restent sans coordonnees :');
        $this->line("  districts : {$missingDistricts} / " . HealthDistrict::count());
        $this->line("  regions   : {$missingRegions} / " . HealthRegion::count());

        // A file that rejected everything is a malformed file, not an empty
        // day's work -- worth an exit code that a script can act on.
        return $applied === 0 && $rejected !== [] ? self::FAILURE : self::SUCCESS;
    }
}
