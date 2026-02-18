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
            // ðŸ‘©â€âš•ï¸ Vie reproductive & hormonale
            [
                'key' => 'is_pregnant',
                'text' => 'ðŸ¤° ÃŠtes-vous actuellement enceinteâ€¯?',
                'type' => 'boolean',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 1,
            ],
            [
                'key' => 'breastfeeding',
                'text' => 'ðŸ¼ Allaitez-vous en ce momentâ€¯?',
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
                'text' => 'âš–ï¸ ÃŠtes-vous mÃ©nopausÃ©eâ€¯?',
                'type' => 'select_one',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'options' => json_encode([
                    ['value' => 'unknown', 'label' => 'Je ne sais pas'],
                    ['value' => 'pre',    'label' => 'Non'],
                    ['value' => 'post-',   'label' => 'Oui, depuis moins de 5 ans'],
                    ['value' => 'post+',   'label' => 'Oui, depuis plus de 5 ans'],
                ]),
                'order' => 3,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'is_pregnant', 'value' => false, 'operator' => '=']
                ]),
            ],

            // ðŸ§¬ Historique mÃ©dical
            [
                'key' => 'family_cancer_history',
                'text' => 'ðŸ‘ª Avez-vous des antÃ©cÃ©dents familiaux de cancer (mÃ¨re, sÅ“ur, tanteâ€¦)â€¯?',
                'type' => 'boolean',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 4,
            ],
            [
                'key' => 'last_screening_date',
                'text' => 'ðŸ“… Quelle est la date de votre dernier dÃ©pistage (frottis, mammographieâ€¦)â€¯?',
                'type' => 'select_one',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'options' => json_encode([
                    ['value' => 'never', 'label' => 'Je nâ€™ai jamais fait de dÃ©pistage'],
                    ['value' => 'over_3_years', 'label' => 'Il y a plus de 3 ans'],
                    ['value' => 'over_5_year', 'label' => 'Oui, mais je ne me souviens plus de la date exacte'],
                    ['value' => 'less_than_1_year', 'label' => 'Il y a moins dâ€™un an'],
                ]),
                'order' => 5,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'menopause_status', 'value' => 'pre', 'operator' => '=']
                ]),
            ],
            [
                'key' => 'screening_type',
                'text' => 'ðŸ§ª Quel type de dÃ©pistage avez-vous effectuÃ© en dernierâ€¯?',
                'type' => 'select_one',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'options' => json_encode([
                    ['value' => 'frottis', 'label' => 'Cancer de col de lâ€™utÃ©rus (frottis)'],
                    ['value' => 'mammographie', 'label' => 'Mammographie'],
                    ['value' => 'autre', 'label' => 'Autre'],
                ]),
                'order' => 6,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'last_screening_date', 'value' =>'never', 'operator' => '!=']
                ]),
            ],
            [
              'key' => 'last_screening_result',
                'text' => 'âœ… Quel Ã©tait le rÃ©sultat de votre dernier dÃ©pistageâ€¯?',
                'type' => 'select_one',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'options' => json_encode([
                    ['value' => 'normal', 'label' => 'Normal'],
                    ['value' => 'abnormal', 'label' => 'Anormal, avec suivi mÃ©dical'],
                    ['value' => 'abnormal_no_followup', 'label' => 'Anormal, sans suivi mÃ©dical'],
                    ['value' => 'dont_know', 'label' => 'Je ne sais pas'],
                ]),
                'order' => 7,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'last_screening_date', 'value' =>'never', 'operator' => '!='],

                ]),
            ],

            // ðŸš¨ Question de Triage Principal (La Bascule)
            [
                'key' => 'primary_symptom_type',
                'text' => 'ðŸš© Ressentez-vous actuellement lâ€™un de ces signes ou une gÃªne particuliÃ¨re ?',
                'type' => 'select_one',
                'bloc_key' => $bloc,
                'answer_target' => 'evaluation',
                'options' => json_encode([
                    ['value' => 'none', 'label' => 'Non, je souhaite faire une Ã©valuation de routine'],
                    ['value' => 'pain_pelvis', 'label' => 'Oui, des douleurs pelviennes'],
                    ['value' => 'pain_mammaries', 'label' => 'Oui, des douleurs mammaires'],
                    ['value' => 'bleeding', 'label' => 'Oui, des saignements inhabituels'],
                    ['value' => 'both', 'label' => 'Oui, les deux (douleurs et saignements)'],
                    ['value' => 'lump', 'label' => 'Oui, jâ€™ai senti une grosseur ou une masse'],
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
                'text' => '🎗️ Avez-vous deja eu un cancer?',
                'type' => 'boolean',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 23,
            ],
            [
                'key' => 'cancer_details',
                'text' => '🎗️ Quel type de cancer avez-vous eu?',
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
                'text' => 'ðŸ©º Avez-vous dâ€™autres maladies chroniques ou conditions mÃ©dicales importantesâ€¯? (diabÃ¨te, hypertension, etc.)',
                'type' => 'text',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 9,
            ],
            [
                'key' => 'current_medications',
                    'text' => 'ðŸ’Š Prenez-vous actuellement des mÃ©dicaments sur ordonnanceâ€¯? Si oui, lesquelsâ€¯?',
                    'type' => 'text',
                    'bloc_key' => $bloc,
                    'answer_target' => 'medical_profile',
                    'order' => 10,
                ],

            // ðŸ©º Vie sexuelle & gynÃ©cologique
            [
                'key' => 'first_sexual_intercourse_age',
                'text' => 'ðŸ”ž Quel Ã¢ge aviez-vous lors de votre premier rapport sexuelâ€¯?',
                'type' => 'number',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'min' => 8,
                'max' => 60,
                'order' => 11,
            ],
            [
                'key' => 'number_of_partners',
                'text' => 'ðŸ‘« Combien de partenaires sexuels avez-vous eu dans votre vieâ€¯?',
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
                'text' => 'ðŸ¤° Combien de grossesses avez-vous eues (y compris fausses couches/IVG)â€¯?',
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
                'text' => 'ðŸš¨ Combien dâ€™avortements/fausses couches avez-vous euesâ€¯?',
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
                'text' => 'ðŸ‘¶ Combien dâ€™enfants avez-vous euâ€¯?',
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

            //ðŸ“ DonnÃ©es biomÃ©triques
            [
                'key' => 'cmu_number',
                'text' => 'ðŸ’³ NumÃ©ro de CMU (si applicable)',
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
                'text' => 'ðŸ“ Quelle est votre taille (en cm)â€¯?',
                'type' => 'number',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'min' => 100,
                'max' => 220,
                'order' => 17,
            ],
            [
                'key' => 'weight',
                'text' => 'âš–ï¸ Quel est votre poids (en kg)â€¯?',
                'type' => 'number',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'min' => 30,
                'max' => 200,
                'order' => 18,
            ],
            [
                'key' => 'blood_type',
                'text' => 'ðŸ©¸ Quel est votre groupe sanguinâ€¯?',
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
                'text' => 'ðŸŒ± Avez-vous des allergies connuesâ€¯? (prÃ©cisez si oui)',
                'type' => 'text',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 20,
            ],
            
            // ðŸŒ«ï¸ Exposition environnementale
            [
                'key' => 'exposed_to_smoke',
                'text' => 'ðŸŒ«ï¸ ÃŠtes-vous exposÃ©e Ã  la fumÃ©e (tabac, feu, etc.)â€¯?',
                'type' => 'boolean',
                'bloc_key' => $bloc,
                'answer_target' => 'medical_profile',
                'order' => 21,
            ],
            [
                'key' => 'exposure_type',
                'text' => 'ðŸŒ«ï¸ Type dâ€™exposition (tabac, feu, pollution, autreâ€¦)',
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
