<?php

namespace Database\Seeders;

use Illuminate\Support\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Evaluation\QuestionGroup;
use App\Models\Evaluation\DynamicQuestionTemplate;

class DynamicQuestionTemplateSeeder extends Seeder
{
    public function run()
    {
        // Greeting + Profil Personnel + Profil Médical + Sein + Gynéco etc.
        $allQuestions = array_merge(
            $greetingInfos,
            $profilPersonnelQuestions,
            $profilMedicalQuestions,
            $seinQuestions,
            $gynecoQuestions
        );

        foreach ($allQuestions as $q) {
            DynamicQuestionTemplate::updateOrCreate(
                ['key' => $q['key']],
                $q
            );
        }
    }
}
