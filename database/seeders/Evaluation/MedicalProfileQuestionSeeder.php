<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Services\QuestionTemplateContractValidator;

class MedicalProfileQuestionSeeder extends Seeder
{
    public function run()
    {
        $validator = new QuestionTemplateContractValidator();
        $group = QuestionGroup::where('key', 'medical_profile')->firstOrFail();

        $bloc = 'medical_profile';

        $questions = [
            // Vie reproductive et hormonale
            [
                'key' => 'is_pregnant',
                'text' => 'Êtes-vous actuellement enceinte ?',
                'type' => 'boolean',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 1,
            ],
            [
                'key' => 'breastfeeding',
                'text' => 'Allaitez-vous en ce moment ?',
                'type' => 'boolean',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 2,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'is_pregnant', 'value' => false, 'operator' => '=']
                ]),
            ],
            [
                'key' => 'menopause_status',
                'text' => 'Êtes-vous ménopausée ?',
                'type' => 'select_one',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'options' => json_encode([
                    ['value' => 'unknown', 'label' => 'Je ne sais pas'],
                    ['value' => 'pre', 'label' => 'Non'],
                    ['value' => 'post-', 'label' => 'Oui, depuis moins de 5 ans'],
                    ['value' => 'post+', 'label' => 'Oui, depuis plus de 5 ans'],
                ]),
                'order' => 3,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'is_pregnant', 'value' => false, 'operator' => '=']
                ]),
            ],

            // Historique médical
            [
                'key' => 'family_cancer_history',
                'text' => 'Avez-vous des antécédents familiaux de cancer (mère, sœur, tante...) ?',
                'type' => 'boolean',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 4,
            ],
            [
                'key' => 'last_screening_date',
                'text' => 'Quelle est la date de votre dernier dépistage (frottis, mammographie...) ?',
                'type' => 'select_one',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'options' => json_encode([
                    ['value' => 'never', 'label' => 'Je n\'ai jamais fait de dépistage'],
                    ['value' => 'over_3_years', 'label' => 'Il y a plus de 3 ans'],
                    ['value' => 'over_5_year', 'label' => 'Oui, mais je ne me souviens plus de la date exacte'],
                    ['value' => 'less_than_1_year', 'label' => 'Il y a moins d\'un an'],
                ]),
                'order' => 5,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'menopause_status', 'value' => 'pre', 'operator' => '=']
                ]),
            ],
            [
                'key' => 'screening_type',
                'text' => 'Quel type de dépistage avez-vous effectué en dernier ?',
                'type' => 'select_one',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'options' => json_encode([
                    ['value' => 'frottis', 'label' => 'Cancer du col de l\'utérus (frottis)'],
                    ['value' => 'mammographie', 'label' => 'Mammographie'],
                    ['value' => 'autre', 'label' => 'Autre'],
                ]),
                'order' => 6,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'last_screening_date', 'value' => 'never', 'operator' => '!=']
                ]),
            ],
            [
                'key' => 'last_screening_result',
                'text' => 'Quel était le résultat de votre dernier dépistage ?',
                'type' => 'select_one',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'options' => json_encode([
                    ['value' => 'normal', 'label' => 'Normal'],
                    ['value' => 'abnormal', 'label' => 'Anormal, avec suivi médical'],
                    ['value' => 'abnormal_no_followup', 'label' => 'Anormal, sans suivi médical'],
                    ['value' => 'dont_know', 'label' => 'Je ne sais pas'],
                ]),
                'order' => 7,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'last_screening_date', 'value' => 'never', 'operator' => '!='],
                ]),
            ],

            // Question de triage principal
            [
                'key' => 'primary_symptom_type',
                'text' => 'Ressentez-vous actuellement l\'un de ces signes ou une gêne particulière ?',
                'type' => 'select_one',
                'bloc_key' => $bloc,
                'answer_target' => 'evaluation',
                'options' => json_encode([
                    ['value' => 'none', 'label' => 'Non, je souhaite faire une évaluation de routine'],
                    ['value' => 'pain_pelvis', 'label' => 'Oui, des douleurs pelviennes'],
                    ['value' => 'pain_mammaries', 'label' => 'Oui, des douleurs mammaires'],
                    ['value' => 'bleeding', 'label' => 'Oui, des saignements inhabituels'],
                    ['value' => 'both', 'label' => 'Oui, les deux (douleurs et saignements)'],
                    ['value' => 'lump', 'label' => 'Oui, j\'ai senti une grosseur ou une masse'],
                ]),
                'order' => 8,
                'routing_rules' => [
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => 'none'], 'next' => 'bloc:lifestyle'],
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => 'pain_pelvis'], 'next' => 'bloc:symptom_cervix'],
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => 'pain_mammaries'], 'next' => 'bloc:symptom_breast'],
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => 'bleeding'], 'next' => 'bloc:symptom_triage'],
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => 'both'], 'next' => 'bloc:symptom_triage'],
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => 'lump'], 'next' => 'bloc:symptom_triage'],
                ],
            ],

            [
                'key' => 'has_cancer',
                'text' => 'Avez-vous déjà eu un cancer ?',
                'type' => 'boolean',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 23,
            ],
            [
                'key' => 'cancer_details',
                'text' => 'Quel type de cancer avez-vous eu ?',
                'type' => 'text',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 24,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'has_cancer', 'value' => true, 'operator' => '=']
                ]),
            ],
            [
                'key' => 'other_diseases',
                'text' => 'Avez-vous d\'autres maladies chroniques ou conditions médicales importantes ? (diabète, hypertension, etc.)',
                'type' => 'text',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 9,
            ],
            [
                'key' => 'current_medications',
                'text' => 'Prenez-vous actuellement des médicaments sur ordonnance ? Si oui, lesquels ?',
                'type' => 'text',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 10,
            ],

            // Vie sexuelle et gynécologique
            [
                'key' => 'first_sexual_intercourse_age',
                'text' => 'Quel âge aviez-vous lors de votre premier rapport sexuel ?',
                'type' => 'number',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'min' => 8,
                'max' => 60,
                'order' => 11,
            ],
            [
                'key' => 'number_of_partners',
                'text' => 'Combien de partenaires sexuels avez-vous eu dans votre vie ?',
                'type' => 'number',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'min' => 0,
                'max' => 100,
                'order' => 12,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'first_sexual_intercourse_age', 'value' => 16, 'operator' => '<']
                ]),
            ],
            [
                'key' => 'number_of_pregnancies',
                'text' => 'Combien de grossesses avez-vous eues (y compris fausses couches/IVG) ?',
                'type' => 'number',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'min' => 0,
                'max' => 20,
                'order' => 13,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'is_pregnant', 'value' => false, 'operator' => '=']
                ]),
            ],
            [
                'key' => 'number_of_abortions',
                'text' => 'Combien d\'avortements/fausses couches avez-vous eues ?',
                'type' => 'number',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'min' => 0,
                'max' => 10,
                'order' => 14,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'number_of_pregnancies', 'value' => 0, 'operator' => '>']
                ]),
            ],
            [
                'key' => 'number_of_children',
                'text' => 'Combien d\'enfants avez-vous eus ?',
                'type' => 'number',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'min' => 0,
                'max' => 15,
                'order' => 15,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'number_of_pregnancies', 'value' => 0, 'operator' => '>']
                ]),
            ],

            // Données biométriques
            [
                'key' => 'cmu_number',
                'text' => 'Numéro de CMU (si applicable)',
                'type' => 'text',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 16,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'cmu', 'value' => true, 'operator' => '=']
                ]),
            ],
            [
                'key' => 'height',
                'text' => 'Quelle est votre taille (en cm) ?',
                'type' => 'number',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'min' => 100,
                'max' => 220,
                'order' => 17,
            ],
            [
                'key' => 'weight',
                'text' => 'Quel est votre poids (en kg) ?',
                'type' => 'number',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'min' => 30,
                'max' => 200,
                'order' => 18,
            ],
            [
                'key' => 'blood_type',
                'text' => 'Quel est votre groupe sanguin ?',
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
                'text' => 'Avez-vous des allergies connues ? (précisez si oui)',
                'type' => 'text',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 20,
            ],

            // Exposition environnementale
            [
                'key' => 'exposed_to_smoke',
                'text' => 'Êtes-vous exposée à la fumée (tabac, feu, etc.) ?',
                'type' => 'boolean',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 21,
            ],
            [
                'key' => 'exposure_type',
                'text' => 'Type d\'exposition (tabac, feu, pollution, autre...)',
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
            $q['question_group_id'] = $group->id;
            $validator->assertValid($q);
            DynamicQuestionTemplate::updateOrCreate(
                ['key' => $q['key']],
                $q
            );
        }
    }
}
