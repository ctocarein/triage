<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Evaluation\DynamicQuestionTemplate;
use App\Models\Evaluation\QuestionGroup;

class PersonalProfileQuestionSeeder extends Seeder
{
    public function run(): void
    {
        // Récupère tous les groupes nécessaires en une seule fois
        $groups = QuestionGroup::whereIn('key', [
            'profile_personal', 'profil_identite', 'profil_socio', 'profil_sante'
        ])->get()->keyBy('key');

         $questions = [
                [
                    'key' => 'first_name',
                    'text' => '🧑‍🦱 Quel est votre prénom ?',
                    'type' => 'text',
                    'bloc_key' => 'profil_identite',
                    'answer_target' => 'personal_profile',
                    'order' => 1,
                    'is_entry_point' => true,
                ],
                [
                    'key' => 'birth_date',
                    'text' => '🎂 Quelle est votre date de naissance ?',
                    'type' => 'date',
                    'bloc_key' => 'profil_identite',
                    'answer_target' => 'personal_profile',
                    'order' => 2,
                    'is_entry_point' => true,
                ],
                [
                    'key' => 'language',
                    'text' => '🗣️ Quelle est votre langue principale ?',
                    'type' => 'select_one',
                    'bloc_key' => 'profil_socio',
                    'options' => ['Français', 'Anglais', 'Malinké', 'Boualé', 'Autre'],
                    'answer_target' => 'personal_profile',
                    'order' => 3,
                ],
                [
                    'key' => 'commune',
                    'text' => '🏠 Dans quelle commune habitez-vous ?',
                    'type' => 'select_one',
                    'bloc_key' => 'profil_socio',
                    'options' => ['Yopougon', 'Cocody', 'Abobo', 'Adjamé', 'Plateau', 'Koumassi', 'Marcory', 'Bingerville', 'Anyama', 'Autre'],
                    'answer_target' => 'personal_profile',
                    'order' => 4,
                ],
                [
                    'key' => 'cmu',
                    'text' => '💳 Bénéficiez-vous de la Couverture Maladie Universelle (CMU) ?',
                    'type' => 'boolean',
                    'bloc_key' => 'profil_socio',
                    'answer_target' => 'personal_profile',
                    'order' => 5,
                ],
                [
                    'key' => 'height',
                    'text' => '📏 Quelle est votre taille (en cm) ?',
                    'type' => 'number',
                    'bloc_key' => 'profil_sante',
                    'answer_target' => 'personal_profile',
                    'order' => 6,
                ],
                [
                    'key' => 'education_level',
                    'text' => '🎓 Quel est votre niveau d’éducation ?',
                    'type' => 'select_one',
                    'bloc_key' => 'profil_socio',
                    'options' => ['Aucun', 'Primaire', 'Secondaire', 'Supérieur', 'Autodidacte'],
                    'answer_target' => 'personal_profile',
                    'order' => 7,
                ],
                [
                    'key' => 'occupation',
                    'text' => '💼 Quelle est votre profession ou activité principale ?',
                    'type' => 'text',
                    'bloc_key' => 'profil_socio',
                    'answer_target' => 'personal_profile',
                    'order' => 8,
                ]
            ];


        foreach ($questions as $q) {
            // Trouve le sous-groupe, sinon fallback sur le groupe principal
            $questionGroup = $groups[$q['bloc_key']] ?? $groups['profile_personal'];

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
                    'visible_if' => $q['visible_if'] ?? null,
                    'risk_weight' => $q['risk_weight'] ?? 0,
                    'order' => $q['order'] ?? 0,
                    'is_entry_point' => $q['is_entry_point'] ?? false,
                    'is_required' => true,
                    // 'image_url' => $q['image_url'] ?? null, // si tu veux ajouter une image plus tard
                    // Les timestamps sont automatiques si Eloquent gère $timestamps = true
                ]
            );
        }
    }
}
