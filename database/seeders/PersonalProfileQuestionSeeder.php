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
                'answer_target' => 'personal_profile',
                'order' => 1,
                'is_entry_point' => true,
            ],
            [
                'key' => 'date_of_birth',
                'text' => 'Quelle est votre date de naissance ?',
                'type' => 'date',
                'answer_target' => 'personal_profile',
                'order' => 2,
                'is_entry_point' => true,
            ],
               [
                'key' => 'language',
                'text' => 'Quelle est votre langue principale ?',
                'type' => "select_one",
                'options' => json_encode([
                    'Français', 'Anglais', 'Malinké', 'Boualé', 'Autre'
                ]),
                'answer_target' => 'personal_profile',
                'order' => 3,
            ],
            [
                'key' => 'commune',
                'text' => 'Dans quelle commune habitez-vous ?',
                'type' => 'select_one',
                'options' => json_encode([
                    'Commune 1', 'Commune 2', 'Commune 3', 'Commune 4', 'Autre'
                ]),
                'answer_target' => 'personal_profile',
                'order' => 4,
            ],
            [
                'key' => 'cmu',
                'text' => 'Bénéficiez-vous de la Couverture Maladie Universelle (CMU) ?',
                'type' => 'boolean',
                'answer_target' => 'personal_profile',
                'order' => 5,
            ],
            [
                'key' => 'height',
                'text' => 'Quelle est votre taille (en cm) ?',
                'type' => 'number',
                'answer_target' => 'personal_profile',
                'order' => 6,
            ],
            [
                'key' => 'education_level',
                'text' => 'Quel est votre niveau d’éducation ?',
                'type' => 'select_one',
                'answer_target' => 'personal_profile',
                'options' => json_encode([
                    'Aucun', 'Primaire', 'Secondaire', 'Supérieur', 'Autodidacte'
                ]),
                'order' => 7,
            ],
            [
                'key' => 'occupation',
                'text' => 'Quelle est votre profession ou activité principale ?',
                'type' => 'text',
                'answer_target' => 'personal_profile',
                'order' => 8,
            ]
        ];

        foreach ($questions as $q) {
            DynamicQuestionTemplate::updateOrCreate(
                ['key' => $q['key']],
                array_merge($q, [
                    'question_group_id' => $group->id,
                    'is_required' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }
    }
}
