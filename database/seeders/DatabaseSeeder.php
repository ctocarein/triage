<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\Evaluation\DynamicQuestionTemplateSeeder;
use Database\Seeders\Evaluation\ThematicBlocSeeder;
use Database\Seeders\Evaluation\QuestionGroupSeeder;
use Database\Seeders\CapsuleSeeder;
use Database\Seeders\CommuneSeeder;
use Database\Seeders\DecisionPathBreastPainSeeder;
use Database\Seeders\DecisionPathFullAssessmentSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CapsuleSeeder::class,
            CommuneSeeder::class,
            ThematicBlocSeeder::class,
            QuestionGroupSeeder::class,
            DynamicQuestionTemplateSeeder::class,
            DecisionPathBreastPainSeeder::class,
            DecisionPathFullAssessmentSeeder::class,
            // Ajoute ici d’autres seeders si besoin, toujours dans l’ordre logique des dépendances
        ]);
    }
}
