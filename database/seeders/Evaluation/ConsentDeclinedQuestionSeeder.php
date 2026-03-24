<?php

namespace Database\Seeders\Evaluation;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Services\Catalog\QuestionTemplateContractValidator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConsentDeclinedQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $validator = new QuestionTemplateContractValidator;

        $questions = [

            [
                'key' => 'consent_declined_thank_you',
                'text' => 'Merci pour votre retour.',
                'type' => 'custom',
                'bloc_key' => 'consent_declined',
                'answer_target' => 'custom',
                'is_required' => false,
                'is_entry_point' => true,
                'order' => 1,
                'routing_rules' => [
                    ['if' => true, 'next' => 'question:consent_declined_whatsapp'],
                ],
            ],
            [
                'key' => 'consent_declined_whatsapp',
                'text' => 'Si vous le souhaitez, laissez votre numéro WhatsApp pour recevoir des informations ou notifications.',
                'type' => 'text',
                'bloc_key' => 'consent_declined',
                'answer_target' => 'custom',
                'is_required' => false,
                'placeholder' => 'Ex: +225 0700000000',
                'meta' => ['allow_skip' => true, 'skip_label' => 'Passer'],
                'order' => 2,
                'routing_rules' => [
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => null], 'next' => 'question:consent_declined_end'],
                    ['if' => true, 'next' => 'question:consent_declined_end'],
                ],
            ],
            [
                'key' => 'consent_declined_end',
                'text' => 'Aucune recommandation ne sera chargée pour ce parcours. Appuyez sur Terminer pour quitter.',
                'type' => 'custom',
                'bloc_key' => 'consent_declined',
                'answer_target' => 'custom',
                'options' => [
                    ['value' => 'done', 'label' => 'Terminer'],
                ],
                'routing_rules' => [
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => 'done'], 'next' => 'question:consent_declined_thank_you'],
                ],
                'is_required' => true,
                'order' => 3,
            ],
        ];

        $validator->assertUniqueOrderPerBloc($questions, static::class);

        foreach ($questions as $q) {
            $q = $validator->normalize($q);
            $validator->assertValid($q);

            DynamicQuestionTemplate::updateOrCreate(
                ['key' => $q['key']],
                $q
            );
        }
    }
}
