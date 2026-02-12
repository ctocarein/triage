<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use Database\Seeders\GreetingSeeder;
use Database\Seeders\Evaluation\ConsentDeclinedQuestionSeeder;
use Database\Seeders\Evaluation\PersonalProfileQuestionSeeder;
use Database\Seeders\Evaluation\MedicalProfileQuestionSeeder;
use Database\Seeders\Evaluation\SymptomBreastQuestionSeeder;
use Database\Seeders\Evaluation\SymptomCervicalQuestionSeeder;

class DynamicQuestionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GreetingSeeder::class,
            ConsentDeclinedQuestionSeeder::class,
            PersonalProfileQuestionSeeder::class,
            MedicalProfileQuestionSeeder::class,
            SymptomBreastQuestionSeeder::class,
            SymptomCervicalQuestionSeeder::class,
            // Ajoute ici d’autres seeders de questions si tu en as (ex : lifestyle, autres symptômes…)
        ]);
    }
}
