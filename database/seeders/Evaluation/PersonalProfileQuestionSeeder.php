<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;

/**
 * @deprecated Remplacé par PatientContextSeeder et PatientDemographicsSeeder.
 *
 * Les questions `birth_date` et `commune` ont été déplacées dans PatientContextSeeder
 * (bloc `patient_context`, position 2 du parcours).
 * Les questions `first_name`, `cmu` et `cmu_number` ont été déplacées dans
 * PatientDemographicsSeeder (bloc `patient_demographics`, position 14).
 *
 * Ce seeder est conservé pour éviter les erreurs si des appels résiduels
 * existent, mais il ne crée plus aucune question.
 */
class PersonalProfileQuestionSeeder extends Seeder
{
    public function run(): void
    {
        // No-op : toutes les questions ont été migrées vers les nouveaux seeders.
    }
}
