<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use Database\Seeders\GreetingSeeder;

/**
 * Orchestrateur des questions — parcours "Premier Rendez-vous".
 *
 * Ordre d'exécution = ordre du parcours patient :
 *   1.  Greeting           (accueil + consentement + raison de visite)
 *   2.  Consentement refusé (bloc de sortie)
 *   3.  PatientContext      (âge + commune — filtrage précoce)
 *   4.  EmergencyScreen     (signaux d'alarme immédiats)
 *   5.  DispatchIntention   (pivot de routage symptomatique)
 *   6.  Symptômes sein
 *   7.  Symptômes col utérin
 *   8.  Dépistage sein allégé
 *   9.  Dépistage col allégé
 *  10.  MedicalBackground   (antécédents généraux)
 *  11.  GynecologicBackground (antécédents gynéco + obstétricaux)
 *  12.  FamilyHistory        (antécédents familiaux)
 *  13.  Lifestyle            (mode de vie)
 *  14.  ScreeningHistory     (historique de dépistage)
 *  15.  PatientDemographics  (données démographiques complètes)
 */
class DynamicQuestionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GreetingSeeder::class,
            ConsentDeclinedQuestionSeeder::class,
            PatientContextSeeder::class,
            EmergencyScreenSeeder::class,
            DispatchIntentionSeeder::class,
            SymptomBreastQuestionSeeder::class,
            SymptomCervicalQuestionSeeder::class,
            SymptomBreastLightQuestionSeeder::class,
            SymptomCervicalLightQuestionSeeder::class,
            MedicalProfileQuestionSeeder::class,
            GynecologicBackgroundSeeder::class,
            PersonalProfileQuestionSeeder::class, // no-op, kept for safety
            LifestyleQuestionSeeder::class,
            ScreeningHistorySeeder::class,
            PatientDemographicsSeeder::class,
        ]);
    }
}
