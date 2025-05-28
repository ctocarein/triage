<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\CapsuleSeeder;
use Database\Seeders\MedicalProfileQuestionSeeder;
use Database\Seeders\PersonalProfileQuestionSeeder;

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
            DynamicQuestionTemplateSeeder::class,
            CapsuleSeeder::class,
            PersonalProfileQuestionSeeder::class,
            MedicalProfileQuestionSeeder::class,
            SymptomBreastQuestionSeeder::class,
            SymptomCervicalQuestionSeeder::class,
            // PathologySymptomSeeder::class,
            // UserSeeder::class,
            // Add other seeders here
        ]);

    }
}
