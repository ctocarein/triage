<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Evaluation\DynamicQuestionTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class GreetingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $greetingInfos = [
            [
                'key' => 'greeting_welcome',
                'text' => '👋 Soyez le bienvenu dans votre espace de santé CHEREH !',
                'type' => 'info',
                'bloc_key' => 'greeting',
                'answer_target' => 'greeting',
                'is_entry_point' => true,
                'order' => 1,
            ],
            [
                'key' => 'greeting_intro',
                'text' => 'Nous allons vous poser quelques questions pour mieux comprendre votre situation de santé et vous accompagner au mieux.',
                'type' => 'info',
                'bloc_key' => 'greeting',
                'answer_target' => 'greeting',
                'is_entry_point' => false,
                'order' => 2,
            ],
            [
                'key' => 'greeting_confidential',
                'text' => '🔒 Toutes vos réponses resteront strictement confidentielles et sécurisées.',
                'type' => 'info',
                'bloc_key' => 'greeting',
                'answer_target' => 'greeting',
                'is_entry_point' => false,
                'order' => 3,
            ],
            [
                'key' => 'greeting_encourage',
                'text' => 'N\'hésitez pas à répondre sincèrement. Plus vos réponses sont précises, plus nos recommandations seront adaptées !',
                'type' => 'info',
                'bloc_key' => 'greeting',
                'answer_target' => 'greeting',
                'is_entry_point' => false,
                'order' => 4,
            ],
        ];

          foreach ($greetingInfos as $q) {
            DynamicQuestionTemplate::updateOrCreate(
                ['key' => $q['key']],
                $q
            );
        }
    }
}
