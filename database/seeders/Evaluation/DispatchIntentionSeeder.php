<?php

namespace Database\Seeders\Evaluation;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Services\Catalog\QuestionTemplateContractValidator;
use Illuminate\Database\Seeder;

class DispatchIntentionSeeder extends Seeder
{
    public function run(): void
    {
        $validator = new QuestionTemplateContractValidator();

        $dispatchIntentionQuestions = [
            [
                'key' => 'dispatch_intention',
                'text' => 'Merci. Nous allons maintenant poursuivre avec les questions les plus adaptees a votre situation.',
                'type' => 'custom',
                'bloc_key' => 'dispatch_intention',
                // "system" n'est pas un answer_target supporte par le schema actuel.
                'answer_target' => 'custom',
                'order' => 1,
                'is_entry_point' => true,
                'routing_rules' => [
                    [
                        'if' => ['source' => 'question', 'key' => 'raison_visite', 'operator' => 'contains', 'value' => 'sein'],
                        'next' => 'bloc:symptom_breast',
                    ],
                    [
                        'if' => ['source' => 'question', 'key' => 'raison_visite', 'operator' => 'contains', 'value' => 'cervical'],
                        'next' => 'bloc:symptom_cervix',
                    ],
                    [
                        'if' => ['source' => 'question', 'key' => 'raison_visite', 'operator' => 'contains', 'value' => 'depistage'],
                        'next' => 'bloc:symptom_breast_light',
                    ],
                ],
            ],
        ];

        $validator->assertUniqueOrderPerBloc($dispatchIntentionQuestions, static::class);

        foreach ($dispatchIntentionQuestions as $question) {
            $question = $validator->normalize($question);
            $validator->assertValid($question);

            DynamicQuestionTemplate::updateOrCreate(
                ['key' => $question['key']],
                $question
            );
        }
    }
}
