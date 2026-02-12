<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Services\QuestionTemplateContractValidator;

class GreetingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $validator = new QuestionTemplateContractValidator();

        $greetingInfos = [
            [
                'key' => 'greeting_welcome',
                'text' => 'Bienvenue sur CHEREH\n\nCHEREH est un outil de sante numerique concu pour aider a reperer plus tot certains signes de risque lies aux cancers feminins.',
                'type' => 'custom',
                'bloc_key' => 'greeting',
                'answer_target' => null,
                'is_entry_point' => true,
                'order' => 1,
            ],
            [
                'key' => 'greeting_intro',
                'text' => "Information importante\n\nCHEREH ne pose pas de diagnostic medical et ne remplace pas un professionnel de sante.\n\nLes resultats fournis servent uniquement a vous orienter.",
                'bloc_key' => 'greeting',
                'is_entry_point' => false,
                'type' => 'custom',
                'answer_target' => null,
                'order' => 2,
            ],
            [
                'key' => 'greeting_confidential',
                'text' => 'Vos reponses resteront strictement confidentielles et securisees.',
                'type' => 'custom',
                'bloc_key' => 'greeting',
                'answer_target' => null,
                'is_entry_point' => false,
                'order' => 3,
            ],
            [
                'key' => 'explicit_consent',
                'text' => 'Acceptez-vous de repondre a ces questions afin de recevoir une orientation medicale personnalisee ?',
                'type' => 'select_one',
                'bloc_key' => 'greeting',
                'answer_target' => 'consent',
                'options' => json_encode([
                    [
                        'value' => 'yes',
                        'label' => 'Oui, j\'accepte',
                    ],
                    [
                        'value' => 'no',
                        'label' => 'Non, je ne veux pas continuer',
                    ],
                ]),
                'routing_rules' => [
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => 'yes'], 'next' => 'bloc:symptom_breast'],
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => 'no'], 'next' => 'bloc:consent_declined'],
                ],
                'order' => 4,
                'is_required' => true,
            ],

        ];

        $validator->assertUniqueOrderPerBloc($greetingInfos, static::class);

        foreach ($greetingInfos as $q) {
            $q = $validator->normalize($q);
            $validator->assertValid($q);
            DynamicQuestionTemplate::updateOrCreate(
                ['key' => $q['key']],
                $q
            );
        }
    }
}
