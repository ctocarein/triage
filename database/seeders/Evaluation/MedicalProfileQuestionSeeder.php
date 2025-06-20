<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Models\Evaluation\DynamicQuestionTemplate;
use App\Models\Evaluation\QuestionGroup;

class MedicalProfileQuestionSeeder extends Seeder
{
    public function run(): void
    {
        // Récupère tous les groupes utiles (parent + sous-groupes)
        $groups = QuestionGroup::whereIn('key', [
            'profile_medical', 'profil_repro', 'profil_familial'
        ])->get()->keyBy('key');

        $questions = [
            [
                'key' => 'menstruation_age',
                'text' => '🩸 À quel âge avez-vous eu vos premières règles ?',
                'type' => 'number',
                'bloc_key' => 'profil_repro',
                'answer_target' => 'medical_profile',
                'order' => 1,
            ],
            [
                'key' => 'menopause_status',
                'text' => '🌸 Êtes-vous ménopausée ?',
                'type' => 'boolean',
                'bloc_key' => 'profil_repro',
                'answer_target' => 'medical_profile',
                'order' => 2,
            ],
            [
                'key' => 'menopause_age',
                'text' => '🎂 Si oui, à quel âge avez-vous eu la ménopause ?',
                'type' => 'number',
                'bloc_key' => 'profil_repro',
                'answer_target' => 'medical_profile',
                'visible_if' => [
                    ['source' => 'question', 'key' => 'menopause_status', 'value' => true, 'operator' => '=']
                ],
                'order' => 3,
            ],
            [
                'key' => 'family_history_cancer',
                'text' => '👩‍👧‍👧 Y a-t-il des antécédents de cancer du sein ou du col dans votre famille ?',
                'type' => 'boolean',
                'bloc_key' => 'profil_familial',
                'answer_target' => 'medical_profile',
                'risk_weight' => 3,
                'order' => 4,
            ],
            [
                'key' => 'hormonal_contraception',
                'text' => '💊 Avez-vous déjà utilisé une contraception hormonale (pilule, implant, etc.) ?',
                'type' => 'boolean',
                'bloc_key' => 'profil_repro',
                'answer_target' => 'medical_profile',
                'order' => 5,
            ],
            [
                'key' => 'hormonal_duration',
                'text' => '📅 Combien d’années avez-vous utilisé une contraception hormonale ?',
                'type' => 'number',
                'bloc_key' => 'profil_repro',
                'answer_target' => 'medical_profile',
                'visible_if' => [
                    ['source' => 'question', 'key' => 'hormonal_contraception', 'value' => true, 'operator' => '=']
                ],
                'order' => 6,
            ],
            [
                'key' => 'pregnancy_history',
                'text' => '🤰 Avez-vous déjà été enceinte ?',
                'type' => 'boolean',
                'bloc_key' => 'profil_repro',
                'answer_target' => 'medical_profile',
                'order' => 7,
            ],
            [
                'key' => 'breastfeeding_history',
                'text' => '🍼 Avez-vous allaité vos enfants ?',
                'type' => 'boolean',
                'bloc_key' => 'profil_repro',
                'answer_target' => 'medical_profile',
                'order' => 8,
            ],
        ];

        foreach ($questions as $q) {
            $questionGroup = $groups[$q['bloc_key']] ?? $groups['profile_medical'] ?? null;
            if (!$questionGroup) {
                throw new \Exception("Groupe manquant pour la question : {$q['key']} (bloc_key : {$q['bloc_key']})");
            }
            DynamicQuestionTemplate::updateOrCreate(
                [
                    'key' => $q['key'],
                    'answer_target' => $q['answer_target'],
                ],
                [
                    'text' => $q['text'],
                    'type' => $q['type'],
                    'bloc_key' => $q['bloc_key'] ?? null,
                    'question_group_id' => $questionGroup->id,
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
// Note: This seeder assumes that the 'profile_medical', 'profil_repro', and 'profil_familial' groups already exist in the database.