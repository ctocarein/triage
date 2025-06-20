<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Models\Evaluation\DynamicQuestionTemplate;
use App\Models\Evaluation\QuestionGroup;

class SymptomBreastQuestionSeeder extends Seeder
{
    public function run(): void
    {
        // On récupère le SOUS-groupe douleur_sein pour lier les questions
        $subGroup = QuestionGroup::where('key', 'douleur_sein')->first();
        // Fallback sur le groupe principal si besoin
        $group = $subGroup ?? QuestionGroup::where('key', 'symptom_breast')->firstOrFail();

        $questions = [
            [
                'key' => 'breast_pain',
                'bloc_key' => 'douleur_sein',
                'text' => '😣 Avez-vous des douleurs au sein ?',
                'type' => 'boolean',
                'answer_target' => 'evaluation',
                'is_entry_point' => true,
                'risk_weight' => 2,
                'order' => 1,
            ],
            [
                'key' => 'breast_pain_cycle',
                'bloc_key' => 'douleur_sein',
                'text' => '🩸 Cette douleur est-elle liée à votre cycle menstruel ?',
                'type' => 'select_one',
                'options' => ['Oui', 'Non', 'Je suis ménopausée', 'Je ne sais pas'],
                'answer_target' => 'evaluation',
                'risk_weight' => 0.3,
                'visible_if' => [
                    ['source' => 'question', 'key' => 'breast_pain', 'value' => true, 'operator' => '='],
                    ['source' => 'profile.medical', 'key' => 'menopause_status', 'value' => 'post', 'operator' => '!='],
                ],
                'order' => 2,
            ],
            [
                'key' => 'breast_pain_location',
                'bloc_key' => 'douleur_sein',
                'text' => '📍 Où ressentez-vous cette douleur ?',
                'type' => 'select_one',
                'options' => ['Sein gauche', 'Sein droit', 'Les deux', 'Autre'],
                'answer_target' => 'evaluation',
                'risk_weight' => 0.2,
                'visible_if' => [
                    ['source' => 'question', 'key' => 'breast_pain', 'value' => true, 'operator' => '=']
                ],
                'order' => 3,
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
                    'is_entry_point' => $q['is_entry_point'] ?? false,
                    'is_required' => true,
                ]
            );
        }
    }
}
