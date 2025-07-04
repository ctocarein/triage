<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\CapsuleSeeder;
use Database\Seeders\CommuneSeeder;
use Database\Seeders\DecisionPathBreastSeeder;
use Database\Seeders\DecisionPathBreastMassSeeder;
use Database\Seeders\DecisionPathBreastPainSeeder;
use Database\Seeders\Evaluation\QuestionGroupSeeder;
use Database\Seeders\DecisionPathFullAssessmentSeeder;
use Database\Seeders\Evaluation\DynamicQuestionTemplateSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CapsuleSeeder::class,
            CommuneSeeder::class,
            QuestionGroupSeeder::class,
            DynamicQuestionTemplateSeeder::class,
            DecisionPathBreastMassSeeder::class,
            DecisionPathBreastPainSeeder::class,
            DecisionPathFullAssessmentSeeder::class,
            // Ajoute ici d’autres seeders si besoin, toujours dans l’ordre logique des dépendances
        ]);
    }
}
