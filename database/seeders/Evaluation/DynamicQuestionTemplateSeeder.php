<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use Database\Seeders\GreetingSeeder;

/**
 * Orchestrateur des questions — parcours "Premier Rendez-vous".
 *
 * Ordre d’exécution = ordre du parcours patient :
 *   1. Greeting (accueil + consentement + raison visite)
 *   2. Consentement refusé (bloc de sortie)
 *   3. Profil personnel (identité, naissance, genre…)
 *   4. Mode de vie (tabac, alcool, activité, poids)
 *   5. Profil médical (antécédents, grossesse, ménopause, VIH…)
 *   6. Symptômes sein
 *   7. Symptômes col utérin
 */
class DynamicQuestionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GreetingSeeder::class,
            ConsentDeclinedQuestionSeeder::class,
            PersonalProfileQuestionSeeder::class,
            LifestyleQuestionSeeder::class,
            MedicalProfileQuestionSeeder::class,
            SymptomBreastQuestionSeeder::class,
            SymptomCervicalQuestionSeeder::class,
        ]);
    }
}
