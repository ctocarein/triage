<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Services\QuestionTemplateContractValidator;

class PersonalProfileQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $validator = new QuestionTemplateContractValidator();

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
                    'text' => '🎂 Quelle est votre année de naissance ?',
                    'type' => 'date',
                    'bloc_key' => 'profil_identite',
                    'answer_target' => 'personal_profile',
                    'options' => json_encode([
                        'format' => 'YYYY',
                        'placeholder' => 'AAAA',
                    ]),
                    'order' => 2,
                    'is_entry_point' => true,
                ],
                [
                    'key' => 'language',
                    'text' => '🗣️La langue vous parlez le mieux ?',
                    'type' => 'text',
                    'bloc_key' => 'profil_socio',
                    'answer_target' => 'personal_profile',
                    'order' => 3,
                ],
                [
                    'key' => 'commune',
                    'text' => '🏠 Dans quelle commune habitez-vous ?',
                    'type' => 'select_one',
                    'bloc_key' => 'profil_socio',
                    'options' => json_encode([
                        ['value' => 'yopougon', 'label' => 'Yopougon'],
                        ['value' => 'cocody', 'label' => 'Cocody'],
                        ['value' => 'abobo', 'label' => 'Abobo'],
                        ['value' => 'adjame', 'label' => 'Adjamé'],
                        ['value' => 'plateau', 'label' => 'Plateau'],
                        ['value' => 'koumassi', 'label' => 'Koumassi'],
                        ['value' => 'marcory', 'label' => 'Marcory'],
                        ['value' => 'bingerville', 'label' => 'Bingerville'],
                        ['value' => 'anyama', 'label' => 'Anyama'],
                        ['value' => 'autre',   'label' => 	'Au-delà de la Côte d’Ivoire (Autre)'],
                    ]),
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
                    'key' => 'marital_status',
                    'text' => '💍 Quel est votre statut matrimonial ?',
                    'type' => 'select_one',
                    'bloc_key' => 'profil_socio',
                    'options' => ['Célibataire', 'Marié(e)', 'Divorcé(e)', 'Veuf(ve)'],
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

        $validator->assertUniqueOrderPerBloc($questions, static::class);


        foreach ($questions as $q) {
            // Trouve le sous-groupe, sinon fallback sur le groupe principal
            $questionGroup = $groups[$q['bloc_key']] ?? $groups['profile_personal'];
            $q = $validator->normalize($q);
            $validator->assertValid($q);

            DynamicQuestionTemplate::updateOrCreate(
                [
                    'key' => $q['key'],
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
