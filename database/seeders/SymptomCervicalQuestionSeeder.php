<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Evaluation\DynamicQuestionTemplate;
use App\Models\Evaluation\QuestionGroup;

class SymptomCervicalQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $group = QuestionGroup::where('key', 'symptom_cervix')->firstOrFail();

        $questions = [
            [
                'key' => 'vaginal_bleeding',
                'text' => 'Avez-vous des saignements vaginaux anormaux ?',
                'type' => 'boolean',
                'risk_weight' => 5,
                'is_entry_point' => true,
                'order' => 1,
            ],
            [
                'key' => 'bleeding_details',
                'text' => 'Quand se produisent ces saignements ?',
                'type' => 'select_multiple',
                'options' => ['Après rapports sexuels', 'En dehors des règles', 'Après la ménopause'],
                'risk_weight' => 4,
                'visible_if' => [
                    ['question' => 'vaginal_bleeding', 'value' => true, 'operator' => '=']
                ],
                'order' => 2,
            ],
            [
                'key' => 'vaginal_discharge',
                'text' => 'Avez-vous des pertes vaginales anormales ?',
                'type' => 'boolean',
                'risk_weight' => 3,
                'order' => 3,
            ],
            [
                'key' => 'discharge_appearance',
                'text' => 'Aspect ou odeur des pertes ?',
                'type' => 'select_one',
                'options' => ['Claires', 'Blanches épaisses', 'Jaunâtres', 'Malodorantes', 'Sanglantes'],
                'risk_weight' => 4,
                'visible_if' => [
                    ['question' => 'vaginal_discharge', 'value' => true, 'operator' => '=']
                ],
                'order' => 4,
            ],
            [
                'key' => 'pelvic_pain',
                'text' => 'Ressentez-vous des douleurs pelviennes ?',
                'type' => 'boolean',
                'risk_weight' => 2,
                'order' => 5,
            ],
            [
                'key' => 'pain_intensity',
                'text' => 'Intensité de la douleur ?',
                'type' => 'select_one',
                'options' => ['Légère', 'Modérée', 'Forte'],
                'risk_weight' => 2,
                'visible_if' => [
                    ['question' => 'pelvic_pain', 'value' => true, 'operator' => '=']
                ],
                'order' => 6,
            ],
            [
                'key' => 'urinary_discomfort',
                'text' => 'Ressentez-vous une gêne en urinant ?',
                'type' => 'boolean',
                'risk_weight' => 2,
                'order' => 7,
            ],
            [
                'key' => 'cervical_symptom_duration',
                'text' => 'Depuis combien de temps avez-vous ces symptômes ?',
                'type' => 'select_one',
                'options' => ['Moins d\'une semaine', '1-4 semaines', '1-3 mois', 'Plus de 3 mois'],
                'risk_weight' => 3,
                'order' => 8,
            ],
            [
                'key' => 'symptoms_worsening',
                'text' => 'Les symptômes se sont-ils aggravés récemment ?',
                'type' => 'boolean',
                'risk_weight' => 2,
                'order' => 9,
            ]
        ];

        foreach ($questions as $q) {
            DynamicQuestionTemplate::updateOrCreate(
                ['key' => $q['key']],
                [
                    'text' => $q['text'],
                    'type' => $q['type'],
                    'answer_target' => 'evaluation',
                    'question_group_id' => $group->id,
                    'options' => isset($q['options']) ? json_encode($q['options']) : null,
                    'visible_if' => isset($q['visible_if']) ? json_encode($q['visible_if']) : null,
                    'risk_weight' => $q['risk_weight'] ?? 0,
                    'order' => $q['order'] ?? 0,
                    'is_required' => true,
                    'is_entry_point' => $q['is_entry_point'] ?? false,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }
    }
}
