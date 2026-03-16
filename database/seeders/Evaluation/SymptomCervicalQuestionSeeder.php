<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Services\Catalog\QuestionTemplateContractValidator;

class SymptomCervicalQuestionSeeder extends Seeder
{
    public function run()
    {
        $validator = new QuestionTemplateContractValidator();
        $group = QuestionGroup::where('key', 'symptom_cervix')->firstOrFail();
        $questions = [
            

            // 1️⃣ Saignement vaginal anormal
            [
                'key' => 'vaginal_bleeding_abnormal',
                'text' => '🩸 Avez-vous eu des saignements vaginaux anormaux (en dehors des règles, après rapport ou après la ménopause) ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_cervix',
                'answer_target' => 'evaluation',
                'is_entry_point' => true,
                'order' => 1,
                'risk_weight' => 5,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/gyneco_bleeding.jpg'),
            ],

            // Branche si Oui
            [
                'key' => 'bleeding_when',
                'text' => 'Quand surviennent ces saignements ?',
                'type' => 'select_multiple',
                'options' => json_encode([
                    ['value' => 'outside_periods',   'label' => 'En dehors des règles'],
                    ['value' => 'after_sex',         'label' => 'Après un rapport sexuel'],
                    ['value' => 'after_menopause',   'label' => 'Après la ménopause'],
                    ['value' => 'unknown',           'label' => 'Je ne sais pas'],
                ]),
                'bloc_key' => 'symptom_cervix',
                'answer_target' => 'evaluation',
                'order' => 2,
                'risk_weight' => 4,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'vaginal_bleeding_abnormal', 'value' => true, 'operator' => '=']
                ]),
            ],
            [
                'key' => 'bleeding_amount',
                'text' => 'Le saignement était-il abondant (nécessité de changer de protection plus souvent, caillots, anémie ressentie) ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_cervix',
                'answer_target' => 'evaluation',
                'order' => 3,
                'risk_weight' => 3,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'vaginal_bleeding_abnormal', 'value' => true, 'operator' => '=']
                ]),
            ],
            [
                'key' => 'bleeding_duration',
                'text' => 'Depuis combien de temps ces saignements durent-ils ?',
                'type' => 'select_one',
                'options' => json_encode([
                    ['value' => 'less_1w', 'label' => 'Moins d\'une semaine'],
                    ['value' => '1_4w',    'label' => '1-4 semaines'],
                    ['value' => '1_3m',    'label' => '1-3 mois'],
                    ['value' => 'more_3m', 'label' => 'Plus de 3 mois'],
                ]),
                'bloc_key' => 'symptom_cervix',
                'answer_target' => 'evaluation',
                'order' => 4,
                'risk_weight' => 2,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'vaginal_bleeding_abnormal', 'value' => true, 'operator' => '=']
                ]),
            ],
            [
                'key' => 'bleeding_pain',
                'text' => 'Avez-vous des douleurs pelviennes associées à ces saignements ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_cervix',
                'answer_target' => 'evaluation',
                'order' => 5,
                'risk_weight' => 3,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'vaginal_bleeding_abnormal', 'value' => true, 'operator' => '=']
                ]),
            ],

            // 2️⃣ Douleurs pelviennes
            [
                'key' => 'pelvic_pain',
                'text' => '🤕 Ressentez-vous des douleurs pelviennes (bas-ventre) ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_cervix',
                'answer_target' => 'evaluation',
                'order' => 6,
                'risk_weight' => 4,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/gyneco_pelvic_pain.jpg'),
            ],
            [
                'key' => 'pelvic_pain_since',
                'text' => 'Depuis combien de temps ces douleurs durent-elles ?',
                'type' => 'select_one',
                'options' => json_encode([
                    ['value' => 'less_1w', 'label' => 'Moins d\'une semaine'],
                    ['value' => '1_4w',    'label' => '1-4 semaines'],
                    ['value' => '1_3m',    'label' => '1-3 mois'],
                    ['value' => 'more_3m', 'label' => 'Plus de 3 mois'],
                ]),
                'bloc_key' => 'symptom_cervix',
                'answer_target' => 'evaluation',
                'order' => 7,
                'risk_weight' => 1,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'pelvic_pain', 'value' => true, 'operator' => '=']
                ]),
            ],
            [
                'key' => 'pelvic_pain_nature',
                'text' => 'Le caractère de cette douleur : permanente, par cycle, brutale ?',
                'type' => 'select_one',
                'options' => json_encode([
                    ['value' => 'permanent',   'label' => 'Permanente'],
                    ['value' => 'cyclic',      'label' => 'Par cycle'],
                    ['value' => 'sudden',      'label' => 'Brutale'],
                    ['value' => 'other',       'label' => 'Autre'],
                ]),
                'bloc_key' => 'symptom_cervix',
                'answer_target' => 'evaluation',
                'order' => 8,
                'risk_weight' => 1,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'pelvic_pain', 'value' => true, 'operator' => '=']
                ]),
            ],

            // 3️⃣ Pertes vaginales inhabituelles
            [
                'key' => 'vaginal_discharge',
                'text' => '🦠 Avez-vous des pertes vaginales inhabituelles (couleur/odeur anormales, abondantes…) ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_cervix',
                'answer_target' => 'evaluation',
                'order' => 9,
                'risk_weight' => 3,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/gyneco_discharge.jpg'),
            ],
            [
                'key' => 'discharge_aspect',
                'text' => 'Quel est l’aspect de ces pertes ?',
                'type' => 'select_one',
                'options' => json_encode([
                    ['value' => 'bloody',      'label' => 'Sanglantes'],
                    ['value' => 'purulent',    'label' => 'Purulentes/blanchâtres'],
                    ['value' => 'malodorous',  'label' => 'Nauséabondes'],
                    ['value' => 'other',       'label' => 'Autre'],
                ]),
                'bloc_key' => 'symptom_cervix',
                'answer_target' => 'evaluation',
                'order' => 10,
                'risk_weight' => 2,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'vaginal_discharge', 'value' => true, 'operator' => '=']
                ]),
            ],

            // 4️⃣ Masse pelvienne
            [
                'key' => 'pelvic_mass',
                'text' => '🎈 Avez-vous une sensation de boule ou de gonflement dans le bas-ventre (masse pelvienne) ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_cervix',
                'answer_target' => 'evaluation',
                'order' => 11,
                'risk_weight' => 4,
                'routing_rules' => [
                    [
                        'if' => ['source' => 'answer', 'operator' => '=', 'value' => true],
                        'next' => 'end_session:urgent_pelvic_mass',
                    ],
                ],
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/gyneco_mass.jpg'),
            ],
            [
                'key' => 'pelvic_mass_since',
                'text' => 'Depuis combien de temps ressentez-vous cette masse ?',
                'type' => 'select_one',
                'options' => json_encode([
                    ['value' => 'less_1w', 'label' => 'Moins d\'une semaine'],
                    ['value' => '1_4w',    'label' => '1-4 semaines'],
                    ['value' => '1_3m',    'label' => '1-3 mois'],
                    ['value' => 'more_3m', 'label' => 'Plus de 3 mois'],
                ]),
                'bloc_key' => 'symptom_cervix',
                'answer_target' => 'evaluation',
                'order' => 12,
                'risk_weight' => 2,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'pelvic_mass', 'value' => true, 'operator' => '=']
                ]),
            ],

            // 5️⃣ Troubles urinaires ou digestifs associés
            [
                'key' => 'associated_troubles',
                'text' => '🚾 Avez-vous des troubles urinaires ou digestifs associés (difficulté à uriner, constipation, etc.) ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_cervix',
                'answer_target' => 'evaluation',
                'order' => 13,
                'risk_weight' => 2,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/gyneco_trouble.jpg'),
            ],
            [
                'key' => 'trouble_type',
                'text' => 'Quel type de trouble ?',
                'type' => 'select_multiple',
                'options' => json_encode([
                    ['value' => 'dysurie',     'label' => 'Difficulté à uriner (dysurie)'],
                    ['value' => 'pollakiurie', 'label' => 'Urines fréquentes (pollakiurie)'],
                    ['value' => 'constipation','label' => 'Constipation'],
                    ['value' => 'tenesme',     'label' => 'Ténesme rectal'],
                    ['value' => 'other',       'label' => 'Autre'],
                ]),
                'bloc_key' => 'symptom_cervix',
                'answer_target' => 'evaluation',
                'order' => 14,
                'risk_weight' => 1,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'associated_troubles', 'value' => true, 'operator' => '=']
                ]),
            ],

            // 6️⃣ Symptômes généraux
            [
                'key' => 'general_fatigue',
                'text' => '🥱 Ressentez-vous une fatigue importante ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_cervix',
                'answer_target' => 'evaluation',
                'order' => 15,
                'risk_weight' => 1,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/gyneco_fatigue.jpg'),
            ],
            [
                'key' => 'general_weightloss',
                'text' => '⚖️ Avez-vous perdu du poids de façon inexpliquée ces derniers mois ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_cervix',
                'answer_target' => 'evaluation',
                'order' => 16,
                'risk_weight' => 2,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/gyneco_weightloss.jpg'),
            ],
        ];

        $validator->assertUniqueOrderPerBloc($questions, static::class);

        foreach ($questions as $q) {
            $q = $validator->normalize($q);
            $q['question_group_id'] = $group->id;
            $validator->assertValid($q);
            DynamicQuestionTemplate::updateOrCreate(
                ['key' => $q['key']],
                $q
            );
        }
    }
}
