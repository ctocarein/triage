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
         $questions = [
            [
                'key' => 'first_name',
                'text' => 'Quel est votre prénom ?',
                'type' => 'text',
                'bloc_key' => 'profile_personal',
                'answer_target' => 'personal_profile',
                'is_entry_point' => true,
                'order' => 1,
                'is_required' => true,
            ],
            [
                'key' => 'last_name',
                'text' => 'Quel est votre nom de famille ?',
                'type' => 'text',
                'bloc_key' => 'profile_personal',
                'answer_target' => 'personal_profile',
                'order' => 2,
                'is_required' => false,
            ],
            [
                'key' => 'birth_date',
                'text' => 'Quelle est votre date de naissance ?',
                'type' => 'date',
                'bloc_key' => 'profile_personal',
                'answer_target' => 'personal_profile',
                'order' => 3,
                'is_required' => true,
            ],
            [
                'key' => 'commune',
                'text' => 'Dans quelle commune habitez-vous ?',
                'type' => 'text',
                'bloc_key' => 'profile_personal',
                'answer_target' => 'personal_profile',
                'order' => 4,
                'is_required' => false,
            ],
            [
                'key' => 'cmu',
                'text' => 'Bénéficiez-vous de la Couverture Maladie Universelle (CMU) ?',
                'type' => 'boolean',
                'bloc_key' => 'profile_personal',
                'answer_target' => 'personal_profile',
                'order' => 5,
                'is_required' => false,
            ],
            [
                'key' => 'education_level',
                'text' => 'Quel est votre niveau d’éducation ?',
                'type' => 'select_one',
                'options' => json_encode([
                    ['value' => 'none', 'label' => 'Aucun'],
                    ['value' => 'primary', 'label' => 'Primaire'],
                    ['value' => 'secondary', 'label' => 'Secondaire'],
                    ['value' => 'superior', 'label' => 'Supérieur'],
                    ['value' => 'self', 'label' => 'Autodidacte'],
                ]),
                'bloc_key' => 'profile_personal',
                'answer_target' => 'personal_profile',
                'order' => 6,
                'is_required' => false,
            ],
            [
                'key' => 'occupation',
                'text' => 'Quelle est votre profession ou activité principale ?',
                'type' => 'text',
                'bloc_key' => 'profile_personal',
                'answer_target' => 'personal_profile',
                'order' => 7,
                'is_required' => false,
            ],
        ];
        // Ajoute les questions au modèle DynamicQuestionTemplate
        foreach ($questions as $q) {
            DynamicQuestionTemplate::updateOrCreate(
                ['key' => $q['key']],
                $q
            );
        }

    }
}
// Note: This seeder assumes that the 'profile_medical', 'profil_repro', and 'profil_familial' groups already exist in the database.