<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\Identity\IdentitySeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CountryCitySeeder::class,
            CapsuleSeeder::class,
            CommuneSeeder::class,
            Evaluation\QuestionGroupSeeder::class,
            Evaluation\DynamicQuestionTemplateSeeder::class,
            Evaluation\ModelVersionsSeeder::class,
            Evaluation\RiskModelsSeeder::class,
            Evaluation\FactsSeeder::class,
            Evaluation\FactMappingsSeeder::class,
            Evaluation\RiskSignalsSeeder::class,
            Evaluation\RiskFlagsSeeder::class,
            DecisionPathBreastMassSeeder::class,
            DecisionPathBreastPainSeeder::class,
            DecisionPathFullAssessmentSeeder::class,
            IdentitySeeder::class,
        ]);
    }
}
