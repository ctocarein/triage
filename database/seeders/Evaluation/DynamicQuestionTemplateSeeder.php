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
 *   3. Mode de vie (tabac, alcool, activité, poids)
 *   4. Profil médical (antécédents, grossesse, ménopause, VIH…)
 *   5. Profil personnel (identité, naissance, CMU…)
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
            LifestyleQuestionSeeder::class,
            DispatchIntentionSeeder::class,
            MedicalProfileQuestionSeeder::class,
            PersonalProfileQuestionSeeder::class,
            SymptomBreastQuestionSeeder::class,
            SymptomCervicalQuestionSeeder::class,
            SymptomBreastLightQuestionSeeder::class,
            SymptomCervicalLightQuestionSeeder::class,
        ]);
    }
}
