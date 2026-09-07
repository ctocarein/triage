<?php

namespace Database\Seeders;

use App\Domains\Organization\Models\HealthDistrict;
use App\Domains\Organization\Models\HealthRegion;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds the national health map from the reference document.
 *
 * The source file keeps its original French keys: it is a ministry document,
 * not our code, and rewriting it would break the ability to diff it against a
 * newer edition. The mapping to English columns happens here.
 *
 * Idempotent -- keyed on `code`, so re-running updates rather than duplicates.
 * That matters because the health map is reissued: the 2025 edition carries 33
 * regions and 113 districts where the previous one carried fewer.
 */
class HealthMapSeeder extends Seeder
{
    public const SOURCE = 'database/data/health_map_ci.json';

    public function run(): void
    {
        $path = base_path(self::SOURCE);

        if (! is_file($path)) {
            throw new RuntimeException("Referentiel sanitaire introuvable : {$path}");
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || ! isset($data['regions']) || ! is_array($data['regions'])) {
            throw new RuntimeException("Referentiel sanitaire illisible : {$path}");
        }

        foreach ($data['regions'] as $region) {
            $model = HealthRegion::updateOrCreate(
                ['code' => $region['code']],
                [
                    'slug' => $region['slug'],
                    'name' => $region['nom'],
                    'seat' => $region['chef_lieu'] ?? null,
                    'pres' => $region['pres'] ?? null,
                ],
            );

            foreach ($region['districts'] ?? [] as $district) {
                HealthDistrict::updateOrCreate(
                    ['code' => $district['code']],
                    [
                        'health_region_id' => $model->id,
                        'slug'             => $district['slug'],
                        'name'             => $district['nom'],

                        // The source carries no district seat. For a district
                        // named after a single town the two coincide, but not
                        // for composite ones -- Adjame-Plateau-Attecoube --,
                        // so guessing would be wrong more often than useful.
                        'seat'             => null,

                        // The source states the assignment was rebuilt from
                        // the administrative map, not taken from an official
                        // table. Nothing here may claim otherwise.
                        'region_assignment_verified' => false,
                        'assignment_note'            => $district['note'] ?? null,
                    ],
                );
            }
        }
    }
}
