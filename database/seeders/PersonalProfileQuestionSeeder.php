<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Evaluation\DynamicQuestionTemplate;
use App\Models\Evaluation\QuestionGroup;

class PersonalProfileQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $group = QuestionGroup::where('key', 'profile_personal')->firstOrFail();

        $questions = [
            [
                'key' => 'first_name',
                'text' => 'Quel est votre prénom ?',
                'type' => 'text',
                'bloc_key' => 'profil_identite',
                'answer_target' => 'personal_profile',
                'order' => 1,
                'is_entry_point' => true,
            ],
            [
                'key' => 'date_of_birth',
                'text' => 'Quelle est votre date de naissance ?',
                'type' => 'date',
                'bloc_key' => 'profil_identite',
                'answer_target' => 'personal_profile',
                'order' => 2,
                'is_entry_point' => true,
            ],
            [
                'key' => 'language',
                'text' => 'Quelle est votre langue principale ?',
                'type' => 'select_one',
                'bloc_key' => 'profil_socio',
                'options' => ['Français', 'Anglais', 'Malinké', 'Boualé', 'Autre'],
                'answer_target' => 'personal_profile',
                'order' => 3,
            ],
            [
                'key' => 'commune',
                'text' => 'Dans quelle commune habitez-vous ?',
                'type' => 'select_one',
                'bloc_key' => 'profil_socio',
                'options' => ['Commune 1', 'Commune 2', 'Commune 3', 'Commune 4', 'Autre'],
                'answer_target' => 'personal_profile',
                'order' => 4,
            ],
            [
                'key' => 'cmu',
                'text' => 'Bénéficiez-vous de la Couverture Maladie Universelle (CMU) ?',
                'type' => 'boolean',
                'bloc_key' => 'profil_socio',
                'answer_target' => 'personal_profile',
                'order' => 5,
            ],
            [
                'key' => 'height',
                'text' => 'Quelle est votre taille (en cm) ?',
                'type' => 'number',
                'bloc_key' => 'profil_sante',
                'answer_target' => 'personal_profile',
                'order' => 6,
            ],
            [
                'key' => 'education_level',
                'text' => 'Quel est votre niveau d’éducation ?',
                'type' => 'select_one',
                'bloc_key' => 'profil_socio',
                'options' => ['Aucun', 'Primaire', 'Secondaire', 'Supérieur', 'Autodidacte'],
                'answer_target' => 'personal_profile',
                'order' => 7,
            ],
            [
                'key' => 'occupation',
                'text' => 'Quelle est votre profession ou activité principale ?',
                'type' => 'text',
                'bloc_key' => 'profil_socio',
                'answer_target' => 'personal_profile',
                'order' => 8,
            ]
        ];

        foreach ($questions as $q) {
            DynamicQuestionTemplate::updateOrCreate(
                ['key' => $q['key']],
                [
                    'text' => $q['text'],
                    'type' => $q['type'],
                    'bloc_key' => $q['bloc_key'] ?? null,
                    'question_group_id' => $group->id,
                    'answer_target' => $q['answer_target'],
                    'options' => isset($q['options']) ? json_encode($q['options']) : null,
                    'visible_if' => $q['visible_if'] ?? null,
                    'risk_weight' => $q['risk_weight'] ?? 0,
                    'order' => $q['order'] ?? 0,
                    'is_entry_point' => $q['is_entry_point'] ?? false,
                    'is_required' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }
    }
}
