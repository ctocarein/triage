<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
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
                'text' => 'Bienvenue sur CHEREH\n\nCHEREH est un outil de santé numérique conçu pour aider à repérer plus tôt certains signes de risque liés aux cancers féminins.',
                'type' => 'custom',
                'bloc_key' => 'greeting',
                'answer_target' => null,
                'is_entry_point' => true,
                'order' => 1,
            ],
            [
                'key' => 'greeting_intro',
                'text' => "⚠️ Information importante\n\nCHEREH ne pose pas de diagnostic médical et ne remplace pas un professionnel de santé.\n\nLes résultats fournis servent uniquement à vous orienter.",
                'bloc_key' => 'greeting',
                'is_entry_point' => false,
                'type' => 'custom',
                'answer_target' => null,
                'order' => 2
            ],

            [
                'key' => 'greeting_confidential',
                'text' => '🔒 Vos réponses resteront strictement confidentielles et sécurisées.',
                'type' => 'custom',
                'bloc_key' => 'greeting',
                'answer_target' => null,
                'is_entry_point' => false,
                'order' => 3,
            ],

            [
                'key' => 'explicit_consent',
                'text' => "Acceptez-vous de répondre à ces questions afin de recevoir une orientation médicale personnalisée ?",
                'type' => 'select_one',
                'bloc_key' => 'greeting',
                'answer_target' => 'consent',
                'options' => json_encode([
                    [
                        'value' => 'yes',
                        'label' => '✅ Oui, j\'accepte',
                    ],
                    [
                        'value' => 'no',
                        'label' => '❌ Non, je ne veux pas continuer',
                    ],
                ]),
                'routing_rules' => [
                    ['if' => ['value' => 'yes'], 'next' => 'bloc:symptom_breast'],
                    ['if' => ['value' => 'no'], 'next' => 'end_session'],
                ],
                'order' => 4,
                'is_required' => true,
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
