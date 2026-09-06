<?php

namespace Database\Seeders\Evaluation;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Services\Catalog\QuestionTemplateContractValidator;
use Illuminate\Database\Seeder;

/**
 * Parcours dépistage sein — version allégée.
 *
 * Affiché uniquement quand raison_visite = 'depistage'.
 * Couvre : autopalpation, dernière mammographie, signaux d'alerte urgents.
 */
class SymptomBreastLightQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $validator = new QuestionTemplateContractValidator();
        $group     = QuestionGroup::where('key', 'symptom_breast')->firstOrFail();

        $questions = [

            // 1 — Autopalpation
            [
                'key'            => 'breast_light_autopalpation',
                'text'           => 'Avez-vous pratiqué l\'autopalpation mammaire au cours des 12 derniers mois ?',
                'type'           => 'boolean',
                'bloc_key'       => 'symptom_breast_light',
                'answer_target'  => 'evaluation',
                'is_entry_point' => true,
                'order'          => 1,
                'risk_weight'    => 0,
            ],

            // 2 — Dernière mammographie
            [
                'key'           => 'breast_light_last_mammo',
                'text'          => 'Avez-vous passé une mammographie au cours des 2 dernières années ?',
                'type'          => 'select_one',
                'options'       => json_encode([
                    ['value' => 'yes',    'label' => 'Oui'],
                    ['value' => 'no',     'label' => 'Non'],
                    ['value' => 'never',  'label' => 'Jamais'],
                    ['value' => 'unknown','label' => 'Je ne sais pas'],
                ]),
                'bloc_key'      => 'symptom_breast_light',
                'answer_target' => 'evaluation',
                'order'         => 2,
                'risk_weight'   => 1,
            ],

            // 3 — Masse palpable
            [
                'key'           => 'breast_light_mass',
                'text'          => 'Avez-vous remarqué une boule ou une masse dans le sein ?',
                'type'          => 'boolean',
                'bloc_key'      => 'symptom_breast_light',
                'answer_target' => 'evaluation',
                'order'         => 3,
                'risk_weight'   => 5,
            ],

            // 4 — Écoulement mamelonnaire
            [
                'key'           => 'breast_light_nipple_discharge',
                'text'          => 'Avez-vous remarqué un écoulement du mamelon ?',
                'type'          => 'boolean',
                'bloc_key'      => 'symptom_breast_light',
                'answer_target' => 'evaluation',
                'order'         => 4,
                'risk_weight'   => 3,
            ],

            // 4.1 — Écoulement sanglant (urgent)
            [
                'key'           => 'breast_light_nipple_bleeding',
                'text'          => 'Cet écoulement est-il sanglant ?',
                'type'          => 'boolean',
                'bloc_key'      => 'symptom_breast_light',
                'answer_target' => 'evaluation',
                'order'         => 5,
                'risk_weight'   => 5,
                'visible_if'    => json_encode([
                    ['source' => 'question', 'key' => 'breast_light_nipple_discharge', 'operator' => '=', 'value' => true],
                ]),
                'routing_rules' => [
                    [
                        'if'   => ['source' => 'answer', 'operator' => '=', 'value' => true],
                        'next' => 'urgent:breast_nipple_bleeding',
                    ],
                ],
            ],

            // 5 — Peau d'orange (urgent)
            [
                'key'           => 'breast_light_skin_orange',
                'text'          => 'La peau de votre sein présente-t-elle un aspect "peau d\'orange" (granuleuse, épaisse) ?',
                'type'          => 'boolean',
                'bloc_key'      => 'symptom_breast_light',
                'answer_target' => 'evaluation',
                'order'         => 6,
                'risk_weight'   => 5,
                'routing_rules' => [
                    [
                        'if'   => ['source' => 'answer', 'operator' => '=', 'value' => true],
                        'next' => 'urgent:breast_skin_orange',
                    ],
                ],
            ],

            // 6 — Douleur au sein
            [
                'key'           => 'breast_light_pain',
                'text'          => 'Avez-vous des douleurs au sein ?',
                'type'          => 'boolean',
                'bloc_key'      => 'symptom_breast_light',
                'answer_target' => 'evaluation',
                'order'         => 7,
                'risk_weight'   => 2,
                'routing_rules' => [
                    ['if' => true, 'next' => 'bloc:symptom_cervical_light'],
                ],
            ],

        ];

        $validator->assertUniqueOrderPerBloc($questions, static::class);

        foreach ($questions as $q) {
            $q = $validator->normalize($q);
            $q['question_group_id'] = $group->id;
            $validator->assertValid($q);
            DynamicQuestionTemplate::updateOrCreate(['key' => $q['key']], $q);
        }
    }
}
