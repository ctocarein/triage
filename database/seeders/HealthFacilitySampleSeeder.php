<?php

namespace Database\Seeders;

use App\Domains\Organization\Models\HealthFacility;
use Illuminate\Database\Seeder;

/**
 * Quatre etablissements d'exemple, en attendant le referentiel reel.
 *
 * Marques `is_sample` : l'application les signale comme tels. Les noms sont
 * volontairement fictifs et aucun telephone n'est pose -- un numero invente
 * pourrait etre celui de quelqu'un.
 *
 * Volontairement hors de DatabaseSeeder, a lancer a la demande :
 *   php artisan db:seed --class="Database\Seeders\HealthFacilitySampleSeeder"
 *
 * A retirer quand les vraies donnees arrivent :
 *   HealthFacility::where('is_sample', true)->delete();
 */
class HealthFacilitySampleSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            ['name' => 'Exemple - Centre de depistage A', 'kind' => 'health_center', 'municipality' => 'Cocody',   'has_mammography' => true],
            ['name' => 'Exemple - Hopital B',             'kind' => 'hospital',      'municipality' => 'Yopougon', 'has_mammography' => true],
            ['name' => 'Exemple - Clinique C',            'kind' => 'clinic',        'municipality' => 'Abobo',    'has_mammography' => false],
            ['name' => 'Exemple - Centre de sante D',     'kind' => 'health_center', 'municipality' => 'Koumassi', 'has_mammography' => false],
        ];

        foreach ($samples as $sample) {
            HealthFacility::updateOrCreate(
                ['name' => $sample['name']],
                $sample + ['is_sample' => true],
            );
        }
    }
}
