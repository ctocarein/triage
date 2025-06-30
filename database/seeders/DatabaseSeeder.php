<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Database\Seeders\CapsuleSeeder;
use Database\Seeders\CommuneSeeder;
use Database\Seeders\GreetingSeeder;
use Database\Seeders\DecisionPathBreastPainSeeder;
use Database\Seeders\Evaluation\ThematicBlocSeeder;
use Database\Seeders\Evaluation\QuestionGroupSeeder;
use Database\Seeders\DecisionPathFullAssessmentSeeder;
use Database\Seeders\Evaluation\SymptomBreastQuestionSeeder;
use Database\Seeders\Evaluation\MedicalProfileQuestionSeeder;
use Database\Seeders\Evaluation\PersonalProfileQuestionSeeder;
use Database\Seeders\Evaluation\SymptomCervicalQuestionSeeder;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $this->call([
            QuestionGroupSeeder::class,
            CapsuleSeeder::class,
            PersonalProfileQuestionSeeder::class,
            MedicalProfileQuestionSeeder::class,
            SymptomBreastQuestionSeeder::class,
            SymptomCervicalQuestionSeeder::class,
            ThematicBlocSeeder::class, 
            CommuneSeeder::class,   
            GreetingSeeder::class,    
            DecisionPathBreastPainSeeder::class,
            DecisionPathFullAssessmentSeeder::class, 
        ]);

    }
}
