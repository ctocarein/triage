<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Services\QuestionTemplateContractValidator;

class MedicalProfileQuestionSeeder extends Seeder
{
    public function run()
    {
        $validator = new QuestionTemplateContractValidator();

        $bloc = 'medical_profile';

        $questions = [
            // 👩‍⚕️ Vie reproductive & hormonale
            [
                'key' => 'is_pregnant',
                'text' => '🤰 Êtes-vous actuellement enceinte ?',
                'type' => 'boolean',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 1,
            ],
            [
                'key' => 'breastfeeding',
                'text' => '🍼 Allaitez-vous en ce moment ?',
                'type' => 'boolean',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 2,
            ],
            [
                'key' => 'menopause_status',
                'text' => '⚖️ Êtes-vous ménopausée ?',
                'type' => 'select_one',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'options' => json_encode([
                    ['value' => 'unknown', 'label' => 'Je ne sais pas'],
                    ['value' => 'pre',    'label' => 'Non, avant la ménopause'],
                    ['value' => 'post',   'label' => 'Oui, après la ménopause'],
                ]),
                'order' => 3,
            ],

            // 🧬 Historique médical
            [
                'key' => 'family_cancer_history',
                'text' => '👪 Avez-vous des antécédents familiaux de cancer (mère, sœur, tante…) ?',
                'type' => 'boolean',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 4,
            ],
            [
                'key' => 'last_screening_date',
                'text' => '📅 Quelle est la date de votre dernier dépistage (frottis, mammographie…) ?',
                'type' => 'date',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 5,
            ],
            [
                'key' => 'screening_type',
                'text' => '🧪 Quel type de dépistage avez-vous effectué en dernier ?',
                'type' => 'text',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 6,
            ],
            [
                'key' => 'has_breast_cancer',
                'text' => '🎗️ Avez-vous eu un cancer du sein ?',
                'type' => 'boolean',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 7,
            ],
            [
                'key' => 'has_ovarian_cancer',
                'text' => '🎗️ Avez-vous eu un cancer des ovaires ?',
                'type' => 'boolean',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 8,
            ],
            [
                'key' => 'has_cervical_cancer',
                'text' => '🎗️ Avez-vous eu un cancer du col de l’utérus ?',
                'type' => 'boolean',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 9,
            ],
            [
                'key' => 'has_other_cancer',
                'text' => '🎗️ Avez-vous eu un autre cancer ?',
                'type' => 'boolean',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 10,
            ],

            // 🩺 Vie sexuelle & gynécologique
            [
                'key' => 'first_sexual_intercourse_age',
                'text' => '🔞 Quel âge aviez-vous lors de votre premier rapport sexuel ?',
                'type' => 'number',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'min' => 8,
                'max' => 60,
                'order' => 11,
            ],
            [
                'key' => 'number_of_partners',
                'text' => '👫 Combien de partenaires sexuels avez-vous eu dans votre vie ?',
                'type' => 'number',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'min' => 0,
                'max' => 100,
                'order' => 12,
            ],
            [
                'key' => 'number_of_pregnancies',
                'text' => '🤰 Combien de grossesses avez-vous eues (y compris fausses couches/IVG) ?',
                'type' => 'number',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'min' => 0,
                'max' => 20,
                'order' => 13,
            ],
            [
                'key' => 'number_of_abortions',
                'text' => '🚨 Combien d’avortements/fausses couches avez-vous eues ?',
                'type' => 'number',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'min' => 0,
                'max' => 10,
                'order' => 14,
            ],
            [
                'key' => 'number_of_children',
                'text' => '👶 Combien d’enfants avez-vous eu ?',
                'type' => 'number',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'min' => 0,
                'max' => 15,
                'order' => 15,
            ],

            //📏 Données biométriques
            [
                'key' => 'cmu_number',
                'text' => '💳 Numéro de CMU (si applicable)',
                'type' => 'text',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 16,
            ],
            [
                'key' => 'height',
                'text' => '📏 Quelle est votre taille (en cm) ?',
                'type' => 'number',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'min' => 100,
                'max' => 220,
                'order' => 17,
            ],
            [
                'key' => 'weight',
                'text' => '⚖️ Quel est votre poids (en kg) ?',
                'type' => 'number',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'min' => 30,
                'max' => 200,
                'order' => 18,
            ],
            [
                'key' => 'blood_type',
                'text' => '🩸 Quel est votre groupe sanguin ?',
                'type' => 'select_one',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'options' => json_encode([
                    ['value' => 'A+', 'label' => 'A+'],
                    ['value' => 'A-', 'label' => 'A-'],
                    ['value' => 'B+', 'label' => 'B+'],
                    ['value' => 'B-', 'label' => 'B-'],
                    ['value' => 'AB+', 'label' => 'AB+'],
                    ['value' => 'AB-', 'label' => 'AB-'],
                    ['value' => 'O+', 'label' => 'O+'],
                    ['value' => 'O-', 'label' => 'O-'],
                    ['value' => 'Null', 'label' => 'Je ne sais pas'],
                ]),
                'order' => 19,
            ],
            [
                'key' => 'allergies',
                'text' => '🌱 Avez-vous des allergies connues ? (précisez si oui)',
                'type' => 'text',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 20,
            ],
            
            // 🌫️ Exposition environnementale
            [
                'key' => 'exposed_to_smoke',
                'text' => '🌫️ Êtes-vous exposée à la fumée (tabac, feu, etc.) ?',
                'type' => 'boolean',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 21,
            ],
            [
                'key' => 'exposure_type',
                'text' => '🌫️ Type d’exposition (tabac, feu, pollution, autre…)',
                'type' => 'text',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 22,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'exposed_to_smoke', 'value' => true, 'operator' => '=']
                ]),
            ],
        ];

        $validator->assertUniqueOrderPerBloc($questions, static::class);

        foreach ($questions as $q) {
            $q = $validator->normalize($q);
            $validator->assertValid($q);
            DynamicQuestionTemplate::updateOrCreate(
                ['key' => $q['key']],
                $q
            );
        }
    }
}
