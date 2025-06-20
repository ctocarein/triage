<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Models\Evaluation\DynamicQuestionTemplate;
use App\Models\Evaluation\QuestionGroup;

class SymptomCervicalQuestionSeeder extends Seeder
{
    public function run(): void
    {
        // On récupère le sous-groupe (saignement_anormal) ou le groupe principal
        $subGroup = QuestionGroup::where('key', 'saignement_anormal')->first();
        $group = $subGroup ?? QuestionGroup::where('key', 'symptom_cervix')->firstOrFail();

        $questions = [
            [
                'key' => 'cervical_bleeding',
                'bloc_key' => 'saignement_anormal',
                'text' => '🩸 Avez-vous des saignements vaginaux anormaux ?',
                'type' => 'boolean',
                'answer_target' => 'evaluation',
                'risk_weight' => 4,
                'is_entry_point' => true,
                'order' => 1,
            ],
            [
                'key' => 'cervical_bleeding_postmenopausal',
                'bloc_key' => 'saignement_anormal',
                'text' => '🌸 Ces saignements surviennent-ils après la ménopause ?',
                'type' => 'boolean',
                'answer_target' => 'evaluation',
                'risk_weight' => 5,
                'visible_if' => [
                    ['source' => 'question', 'key' => 'cervical_bleeding', 'value' => true, 'operator' => '='],
                    ['source' => 'profile.medical', 'key' => 'menopause_status', 'value' => 'post', 'operator' => '='],
                ],
                'order' => 2,
            ],
            [
                'key' => 'cervical_bleeding_intercourse',
                'bloc_key' => 'saignement_anormal',
                'text' => '❤️ Les saignements se produisent-ils après un rapport sexuel ?',
                'type' => 'boolean',
                'answer_target' => 'evaluation',
                'risk_weight' => 3,
                'visible_if' => [
                    ['source' => 'question', 'key' => 'cervical_bleeding', 'value' => true, 'operator' => '='],
                ],
                'order' => 3,
            ],
            [
                'key' => 'cervical_bleeding_duration',
                'bloc_key' => 'saignement_anormal',
                'text' => '⏳ Depuis combien de temps observez-vous ces saignements ?',
                'type' => 'select_one',
                'options' => ['Moins d’une semaine', '1-4 semaines', '1-3 mois', 'Plus de 3 mois'],
                'answer_target' => 'evaluation',
                'risk_weight' => 2,
                'visible_if' => [
                    ['source' => 'question', 'key' => 'cervical_bleeding', 'value' => true, 'operator' => '='],
                ],
                'order' => 4,
            ],
        ];

        foreach ($questions as $q) {
            DynamicQuestionTemplate::updateOrCreate(
                [
                    'key' => $q['key'],
                    'answer_target' => $q['answer_target'],
                ],
                [
                    'question_group_id' => $group->id,
                    'text' => $q['text'],
                    'bloc_key' => $q['bloc_key'] ?? null,
                    'type' => $q['type'],
                    'answer_target' => $q['answer_target'],
                    'options' => isset($q['options']) ? json_encode($q['options']) : null,
                    'visible_if' => isset($q['visible_if']) ? json_encode($q['visible_if']) : null,
                    'risk_weight' => $q['risk_weight'] ?? 0,
                    'order' => $q['order'] ?? 0,
                    'is_required' => true,
                    'is_entry_point' => $q['is_entry_point'] ?? false,
                ]
            );
        }
    }
}
