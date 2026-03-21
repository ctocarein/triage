<?php

namespace Database\Seeders\Evaluation;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Services\Catalog\QuestionTemplateContractValidator;
use Illuminate\Database\Seeder;

/**
 * Parcours dépistage col utérin — version allégée.
 *
 * Affiché uniquement quand raison_visite = 'depistage' ET âge >= 21.
 * Couvre : dernier frottis, vaccination HPV, signaux d'alerte urgents.
 */
class SymptomCervicalLightQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $validator = new QuestionTemplateContractValidator();
        $group     = QuestionGroup::where('key', 'symptom_cervix')->firstOrFail();

        $questions = [

            // 1 — Dernier frottis
            [
                'key'            => 'cervix_light_last_pap',
                'text'           => 'Avez-vous passé un frottis cervical (Pap test) au cours des 3 dernières années ?',
                'type'           => 'select_one',
                'options'        => json_encode([
                    ['value' => 'yes',    'label' => 'Oui'],
                    ['value' => 'no',     'label' => 'Non'],
                    ['value' => 'never',  'label' => 'Jamais'],
                    ['value' => 'unknown','label' => 'Je ne sais pas'],
                ]),
                'bloc_key'       => 'symptom_cervical_light',
                'answer_target'  => 'evaluation',
                'is_entry_point' => true,
                'order'          => 1,
                'risk_weight'    => 1,
            ],

            // 2 — Vaccination HPV
            [
                'key'           => 'cervix_light_hpv_vaccine',
                'text'          => 'Avez-vous reçu le vaccin contre le papillomavirus (HPV) ?',
                'type'          => 'select_one',
                'options'       => json_encode([
                    ['value' => 'yes',    'label' => 'Oui, vaccination complète'],
                    ['value' => 'partial','label' => 'Oui, vaccination partielle'],
                    ['value' => 'no',     'label' => 'Non'],
                    ['value' => 'unknown','label' => 'Je ne sais pas'],
                ]),
                'bloc_key'      => 'symptom_cervical_light',
                'answer_target' => 'evaluation',
                'order'         => 2,
                'risk_weight'   => 0,
            ],

            // 3 — Saignements anormaux
            [
                'key'           => 'cervix_light_bleeding',
                'text'          => 'Avez-vous des saignements vaginaux en dehors des règles ou après un rapport sexuel ?',
                'type'          => 'boolean',
                'bloc_key'      => 'symptom_cervical_light',
                'answer_target' => 'evaluation',
                'order'         => 3,
                'risk_weight'   => 5,
            ],

            // 4 — Pertes inhabituelles
            [
                'key'           => 'cervix_light_discharge',
                'text'          => 'Avez-vous des pertes vaginales inhabituelles (couleur ou odeur anormales) ?',
                'type'          => 'boolean',
                'bloc_key'      => 'symptom_cervical_light',
                'answer_target' => 'evaluation',
                'order'         => 4,
                'risk_weight'   => 3,
            ],

            // 5 — Douleurs pelviennes
            [
                'key'           => 'cervix_light_pelvic_pain',
                'text'          => 'Ressentez-vous des douleurs pelviennes (bas-ventre) persistantes ?',
                'type'          => 'boolean',
                'bloc_key'      => 'symptom_cervical_light',
                'answer_target' => 'evaluation',
                'order'         => 5,
                'risk_weight'   => 3,
            ],

            // 6 — Masse pelvienne (urgent)
            [
                'key'           => 'cervix_light_pelvic_mass',
                'text'          => 'Avez-vous une sensation de boule ou de gonflement dans le bas-ventre ?',
                'type'          => 'boolean',
                'bloc_key'      => 'symptom_cervical_light',
                'answer_target' => 'evaluation',
                'order'         => 6,
                'risk_weight'   => 4,
                'routing_rules' => [
                    [
                        'if'   => ['source' => 'answer', 'operator' => '=', 'value' => true],
                        'next' => 'end_session:urgent_pelvic_mass',
                    ],
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
