<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Evaluation\DynamicQuestionTemplate;
use App\Models\Evaluation\QuestionGroup;

class SymptomBreastQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $group = QuestionGroup::where('key', 'symptom_breast')->firstOrFail();

        $questions = [
            [
                'key' => 'breast_pain',
                'text' => 'Avez-vous des douleurs au sein ?',
                'type' => 'boolean',
                'risk_weight' => 2,
                'is_entry_point' => true,
                'order' => 1,
            ],
            [
                'key' => 'breast_pain_location',
                'text' => 'Localisation de la douleur (quel sein) ?',
                'type' => 'select_one',
                'options' => ['Sein droit', 'Sein gauche', 'Les deux'],
                'risk_weight' => 0,
                'visible_if' => [
                    ['question' => 'breast_pain', 'value' => true, 'operator' => '=']
                ],
                'order' => 2,
            ],
            [
                'key' => 'breast_lump',
                'text' => 'Avez-vous remarqué une masse ou grosseur dans le sein ?',
                'type' => 'boolean',
                'risk_weight' => 5,
                'order' => 3,
            ],
            [
                'key' => 'breast_lump_details',
                'text' => 'Décrivez la masse/grosseur (taille, mobilité, etc.)',
                'type' => 'textarea',
                'risk_weight' => 0,
                'visible_if' => [
                    ['question' => 'breast_lump', 'value' => true, 'operator' => '=']
                ],
                'order' => 4,
            ],
            [
                'key' => 'nipple_discharge',
                'text' => 'Avez-vous des écoulements par le mamelon ?',
                'type' => 'boolean',
                'risk_weight' => 4,
                'order' => 5,
            ],
            [
                'key' => 'nipple_discharge_color',
                'text' => 'Couleur de l\'écoulement ?',
                'type' => 'select_one',
                'options' => ['Clair', 'Laiteux', 'Sanglant', 'Jaune', 'Vert'],
                'risk_weight' => 6,
                'visible_if' => [
                    ['question' => 'nipple_discharge', 'value' => true, 'operator' => '=']
                ],
                'order' => 6,
            ],
            [
                'key' => 'skin_changes',
                'text' => 'Avez-vous remarqué des changements de la peau du sein ?',
                'type' => 'boolean',
                'risk_weight' => 5,
                'order' => 7,
            ],
            [
                'key' => 'skin_changes_type',
                'text' => 'Quel type de changement cutané ?',
                'type' => 'select_multiple',
                'options' => ['Rougeur', 'Peau d\'orange', 'Rétraction', 'Ulcération', 'Eczéma'],
                'risk_weight' => 5,
                'visible_if' => [
                    ['question' => 'skin_changes', 'value' => true, 'operator' => '=']
                ],
                'order' => 8,
            ],
            [
                'key' => 'symptom_duration',
                'text' => 'Depuis combien de temps avez-vous ces symptômes ?',
                'type' => 'select_one',
                'options' => ['Moins d\'une semaine', '1-4 semaines', '1-3 mois', 'Plus de 3 mois'],
                'risk_weight' => 4,
                'order' => 9,
            ],
            [
                'key' => 'symptom_worsening',
                'text' => 'Les symptômes se sont-ils aggravés récemment ?',
                'type' => 'boolean',
                'risk_weight' => 3,
                'order' => 10,
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

