<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Services\Catalog\QuestionTemplateContractValidator;

class MedicalProfileQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $validator    = new QuestionTemplateContractValidator();
        $medGroup     = QuestionGroup::where('key', 'medical_profile')->firstOrFail();
        $familyGroup  = QuestionGroup::where('key', 'profil_familial')->firstOrFail();

        // ----------------------------------------------------------------
        // Questions du bloc `medical_profile`
        // ----------------------------------------------------------------
 $medicalBackgroundQuestions = [
    [
        'key'           => 'is_pregnant',
        'text'          => 'Êtes-vous actuellement enceinte ?',
        'type'          => 'boolean',
        'bloc_key'      => 'medical_background',
        'answer_target' => 'medical_profile',
        'order'         => 1,
        'is_entry_point'=> true,
    ],
    [
        'key'           => 'breastfeeding',
        'text'          => 'Allaitez-vous en ce moment ?',
        'type'          => 'boolean',
        'bloc_key'      => 'medical_background',
        'answer_target' => 'medical_profile',
        'order'         => 2,
        'visible_if'    => json_encode([
            ['source' => 'question', 'key' => 'is_pregnant', 'operator' => '=', 'value' => false],
        ]),
    ],
    [
        'key'           => 'menopause_status',
        'text'          => 'Êtes-vous ménopausée ?',
        'type'          => 'select_one',
        'bloc_key'      => 'medical_background',
        'answer_target' => 'medical_profile',
        'order'         => 3,
        'visible_if'    => json_encode([
            ['source' => 'question', 'key' => 'is_pregnant', 'operator' => '=', 'value' => false],
        ]),
        'options'       => json_encode([
            ['value' => 'unknown', 'label' => 'Je ne sais pas'],
            ['value' => 'pre', 'label' => 'Non'],
            ['value' => 'post_lt_5y', 'label' => 'Oui, depuis moins de 5 ans'],
            ['value' => 'post_gte_5y', 'label' => 'Oui, depuis plus de 5 ans'],
        ]),
    ],
    [
        'key'           => 'last_screening_date',
        'text'          => 'Quelle est la date de votre dernier dépistage (frottis, mammographie…) ?',
        'type'          => 'select_one',
        'bloc_key'      => 'medical_background',
        'answer_target' => 'medical_profile',
        'order'         => 4,
        'options'       => json_encode([
            ['value' => 'never', 'label' => 'Je n\'ai jamais fait de dépistage'],
            ['value' => 'lt_1y', 'label' => 'Il y a moins d\'un an'],
            ['value' => 'gt_3y', 'label' => 'Il y a plus de 3 ans'],
            ['value' => 'unknown', 'label' => 'Je ne me souviens plus de la date exacte'],
        ]),
    ],
    [
        'key'           => 'screening_type',
        'text'          => 'Quel type de dépistage avez-vous effectué en dernier ?',
        'type'          => 'select_one',
        'bloc_key'      => 'medical_background',
        'answer_target' => 'medical_profile',
        'order'         => 5,
        'visible_if'    => json_encode([
            ['source' => 'question', 'key' => 'last_screening_date', 'operator' => '!=', 'value' => 'never'],
        ]),
        'options'       => json_encode([
            ['value' => 'frottis', 'label' => 'Frottis (col de l\'utérus)'],
            ['value' => 'mammographie', 'label' => 'Mammographie'],
            ['value' => 'autre', 'label' => 'Autre'],
        ]),
    ],
    [
        'key'           => 'last_screening_result',
        'text'          => 'Quel était le résultat de votre dernier dépistage ?',
        'type'          => 'select_one',
        'bloc_key'      => 'medical_background',
        'answer_target' => 'medical_profile',
        'order'         => 6,
        'visible_if'    => json_encode([
            ['source' => 'question', 'key' => 'last_screening_date', 'operator' => '!=', 'value' => 'never'],
        ]),
        'options'       => json_encode([
            ['value' => 'normal', 'label' => 'Normal'],
            ['value' => 'abnormal', 'label' => 'Anormal, avec suivi médical'],
            ['value' => 'abnormal_no_followup', 'label' => 'Anormal, sans suivi médical'],
            ['value' => 'dont_know', 'label' => 'Je ne sais pas'],
        ]),
    ],
    [
        'key'           => 'other_diseases',
        'text'          => 'Avez-vous des maladies chroniques ou conditions médicales importantes ? (diabète, hypertension…)',
        'type'          => 'text',
        'bloc_key'      => 'medical_background',
        'answer_target' => 'medical_profile',
        'order'         => 7,
    ],
    [
        'key'           => 'current_medications',
        'text'          => 'Prenez-vous actuellement des médicaments sur ordonnance ? Si oui, lesquels ?',
        'type'          => 'text',
        'bloc_key'      => 'medical_background',
        'answer_target' => 'medical_profile',
        'order'         => 8,
    ],
    [
        'key'           => 'number_of_pregnancies',
        'text'          => 'Combien de grossesses avez-vous eues (y compris fausses couches / IVG) ?',
        'type'          => 'number',
        'bloc_key'      => 'medical_background',
        'answer_target' => 'medical_profile',
        'min'           => 0,
        'max'           => 20,
        'order'         => 9,
        'visible_if'    => json_encode([
            ['source' => 'question', 'key' => 'is_pregnant', 'operator' => '=', 'value' => false],
        ]),
    ],
    [
        'key'           => 'number_of_abortions',
        'text'          => 'Combien d\'avortements / fausses couches avez-vous eus ?',
        'type'          => 'number',
        'bloc_key'      => 'medical_background',
        'answer_target' => 'medical_profile',
        'min'           => 0,
        'max'           => 10,
        'order'         => 10,
        'visible_if'    => json_encode([
            ['source' => 'question', 'key' => 'number_of_pregnancies', 'operator' => '>', 'value' => 0],
        ]),
    ],
    [
        'key'           => 'number_of_children',
        'text'          => 'Combien d\'enfants avez-vous eus ?',
        'type'          => 'number',
        'bloc_key'      => 'medical_background',
        'answer_target' => 'medical_profile',
        'min'           => 0,
        'max'           => 15,
        'order'         => 11,
        'visible_if'    => json_encode([
            ['source' => 'question', 'key' => 'number_of_pregnancies', 'operator' => '>', 'value' => 0],
        ]),
    ],
    [
        'key'           => 'blood_type',
        'text'          => 'Quel est votre groupe sanguin ?',
        'type'          => 'select_one',
        'bloc_key'      => 'medical_background',
        'answer_target' => 'medical_profile',
        'order'         => 12,
        'options'       => json_encode([
            ['value' => 'A+', 'label' => 'A+'],
            ['value' => 'A-', 'label' => 'A-'],
            ['value' => 'B+', 'label' => 'B+'],
            ['value' => 'B-', 'label' => 'B-'],
            ['value' => 'AB+', 'label' => 'AB+'],
            ['value' => 'AB-', 'label' => 'AB-'],
            ['value' => 'O+', 'label' => 'O+'],
            ['value' => 'O-', 'label' => 'O-'],
            ['value' => 'unknown', 'label' => 'Je ne sais pas'],
        ]),
    ],
    [
        'key'           => 'allergies',
        'text'          => 'Avez-vous des allergies connues ? (précisez si oui)',
        'type'          => 'text',
        'bloc_key'      => 'medical_background',
        'answer_target' => 'medical_profile',
        'order'         => 13,
    ],
    [
        'key'           => 'exposed_to_smoke',
        'text'          => 'Êtes-vous exposée à la fumée (tabac passif, feu de bois, pollution…) ?',
        'type'          => 'boolean',
        'bloc_key'      => 'medical_background',
        'answer_target' => 'medical_profile',
        'order'         => 14,
    ],
    [
        'key'           => 'exposure_type',
        'text'          => 'Quel type d\'exposition ? (tabac, feu, pollution, autre…)',
        'type'          => 'text',
        'bloc_key'      => 'medical_background',
        'answer_target' => 'medical_profile',
        'order'         => 15,
        'visible_if'    => json_encode([
            ['source' => 'question', 'key' => 'exposed_to_smoke', 'operator' => '=', 'value' => true],
        ]),
    ],
    [
        'key'           => 'has_cancer',
        'text'          => 'Avez-vous déjà eu un cancer ?',
        'type'          => 'boolean',
        'bloc_key'      => 'medical_background',
        'answer_target' => 'medical_profile',
        'order'         => 16,
        'routing_rules' => [
            ['if' => ['source' => 'answer', 'operator' => '=', 'value' => false], 'next' => 'bloc:family_history'],
        ],
    ],
    [
        'key'           => 'cancer_details',
        'text'          => 'Quel type de cancer avez-vous eu ?',
        'type'          => 'text',
        'bloc_key'      => 'medical_background',
        'answer_target' => 'medical_profile',
        'order'         => 17,
        'visible_if'    => json_encode([
            ['source' => 'question', 'key' => 'has_cancer', 'operator' => '=', 'value' => true],
        ]),
        'routing_rules' => [
            ['if' => true, 'next' => 'bloc:family_history'],
        ],
    ],
];

        // ----------------------------------------------------------------
        // Questions du bloc `family_history`
        // ----------------------------------------------------------------
        $familyQuestions = [
            [
                'key'           => 'family_cancer_history',
                'text'          => 'Avez-vous des antécédents familiaux de cancer (mère, sœur, tante…) ?',
                'type'          => 'boolean',
                'bloc_key'      => 'family_history',
                'answer_target' => 'medical_profile',
                'order'         => 1,
                'routing_rules' => [
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => false], 'next' => 'bloc:dispatch_intention'],
                ],
            ],
            [
                'key'           => 'family_cancer_breast',
                'text'          => 'Un membre de votre famille proche a-t-il eu un cancer du sein ?',
                'type'          => 'boolean',
                'bloc_key'      => 'family_history',
                'answer_target' => 'medical_profile',
                'order'         => 2,
                'risk_weight'   => 3,
                'visible_if'    => json_encode([
                    ['source' => 'question', 'key' => 'family_cancer_history', 'operator' => '=', 'value' => true],
                ]),
            ],
            [
                'key'           => 'family_cancer_cervix',
                'text'          => 'Un membre de votre famille proche a-t-il eu un cancer du col de l\'utérus ?',
                'type'          => 'boolean',
                'bloc_key'      => 'family_history',
                'answer_target' => 'medical_profile',
                'order'         => 3,
                'risk_weight'   => 2,
                'visible_if'    => json_encode([
                    ['source' => 'question', 'key' => 'family_cancer_history', 'operator' => '=', 'value' => true],
                ]),
            ],
            [
                'key'           => 'family_cancer_ovary',
                'text'          => 'Un membre de votre famille proche a-t-il eu un cancer de l\'ovaire ?',
                'type'          => 'boolean',
                'bloc_key'      => 'family_history',
                'answer_target' => 'medical_profile',
                'order'         => 4,
                'risk_weight'   => 2,
                'visible_if'    => json_encode([
                    ['source' => 'question', 'key' => 'family_cancer_history', 'operator' => '=', 'value' => true],
                ]),
                'routing_rules' => [
                    ['if' => true, 'next' => 'bloc:dispatch_intention'],
                ],
            ],
        ];

        // Valider et persister les questions médicales
        $validator->assertUniqueOrderPerBloc($medicalBackgroundQuestions, static::class . '::medical');
        foreach ($medicalBackgroundQuestions as $q) {
            $q = $validator->normalize($q);
            $validator->assertValid($q);
            DynamicQuestionTemplate::updateOrCreate(
                ['key' => $q['key']],
                array_merge($q, ['question_group_id' => $medGroup->id])
            );
        }

        // Valider et persister les questions familiales
        $validator->assertUniqueOrderPerBloc($familyQuestions, static::class . '::family');
        foreach ($familyQuestions as $q) {
            $q = $validator->normalize($q);
            $validator->assertValid($q);
            DynamicQuestionTemplate::updateOrCreate(
                ['key' => $q['key']],
                array_merge($q, ['question_group_id' => $familyGroup->id])
            );
        }

        // Désactiver primary_symptom_type (remplacé par raison_visite dans greeting)
        DynamicQuestionTemplate::where('key', 'primary_symptom_type')
            ->update(['is_active' => false]);

        // Supprimer les doublons biométriques (remplacés par poids_kg/taille_cm dans lifestyle)
        DynamicQuestionTemplate::whereIn('key', ['height', 'weight'])->delete();
    }
}
