<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Services\QuestionTemplateContractValidator;

class SymptomBreastQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $validator = new QuestionTemplateContractValidator();
        // On récupère le SOUS-groupe douleur_sein pour lier les questions
        $subGroup = QuestionGroup::where('key', 'symptom_breast_pain')->first();
        // Fallback sur le groupe principal si besoin
        $group = $subGroup ?? QuestionGroup::where('key', 'symptom_breast')->firstOrFail();

        $questions = [

            // 1️⃣ Douleur au sein (entrée)
            [
                'key' => 'breast_pain',
                'text' => '😣 Avez-vous des douleurs au sein ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_breast',
                'answer_target' => 'evaluation',
                'is_entry_point' => true,
                'order' => 1,
                'risk_weight' => 2,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/breast_pain.jpg'),
            ],

            // 1.1 Caractère cyclique de la douleur (si douleur)
            [
                'key' => 'breast_pain_cycle',
                'text' => '🩸 Cette douleur est-elle liée à votre cycle menstruel ?',
                'type' => 'select_one',
                'options' => json_encode([
                    ['value' => 'yes', 'label' => 'Oui'],
                    ['value' => 'no', 'label' => 'Non'],
                    ['value' => 'menopause', 'label' => 'Je suis ménopausée'],
                    ['value' => 'unknown', 'label' => 'Je ne sais pas'],
                ]),
                'bloc_key' => 'symptom_breast',
                'answer_target' => 'evaluation',
                'order' => 2,
                'risk_weight' => 1,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/breast_pain_cycle.jpg'),
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'breast_pain', 'value' => true, 'operator' => '=']
                ]),
            ],

            // 1.2 Localisation de la douleur (si douleur)
            [
                'key' => 'breast_pain_location',
                'text' => '📍 Où ressentez-vous cette douleur ?',
                'type' => 'select_one',
                'options' => json_encode([
                    ['value' => 'left', 'label' => 'Sein gauche'],
                    ['value' => 'right', 'label' => 'Sein droit'],
                    ['value' => 'both', 'label' => 'Les deux'],
                    ['value' => 'other', 'label' => 'Autre'],
                ]),
                'bloc_key' => 'symptom_breast',
                'answer_target' => 'evaluation',
                'order' => 3,
                'risk_weight' => 0,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/breast_pain_location.jpg'),
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'breast_pain', 'value' => true, 'operator' => '=']
                ]),
            ],

            // 1.3 Intensité de la douleur (si douleur)
            [
                'key' => 'breast_pain_severity',
                'text' => '🔢 Sur une échelle de 0 à 10, quelle est l’intensité de la douleur ?',
                'type' => 'slider',
                'bloc_key' => 'symptom_breast',
                'answer_target' => 'evaluation',
                'order' => 4,
                'risk_weight' => 0,
                'min' => 0,
                'max' => 10,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/breast_pain_severity.jpg'),
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'breast_pain', 'value' => true, 'operator' => '=']
                ]),
            ],

            // 1.4 Réveil nocturne (si douleur)
            [
                'key' => 'breast_pain_wakes_up',
                'text' => '🌙 La douleur du sein vous réveille-t-elle la nuit ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_breast',
                'answer_target' => 'evaluation',
                'order' => 5,
                'risk_weight' => 2,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/breast_pain_wakes_up.jpg'),
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'breast_pain', 'value' => true, 'operator' => '=']
                ]),
            ],

            // 2️⃣ Masse/boule dans le sein
            [
                'key' => 'breast_mass',
                'text' => '🔍 Avez-vous remarqué une boule ou une masse dans le sein ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_breast',
                'answer_target' => 'evaluation',
                'order' => 6,
                'risk_weight' => 5,
                'routing_rules' => [
                    [
                        'if' => ['source' => 'answer', 'operator' => '=', 'value' => true],
                        'next' => 'bloc:medical_profile',
                    ],
                ],
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/breast_mass.jpg'),
            ],

            // 2.1 Changement de taille/aspect masse (si masse)
            [
                'key' => 'breast_mass_change',
                'text' => '🔎 La boule a-t-elle changé rapidement de taille ou d’aspect ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_breast',
                'answer_target' => 'evaluation',
                'order' => 7,
                'risk_weight' => 3,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/breast_mass_change.jpg'),
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'breast_mass', 'value' => true, 'operator' => '=']
                ]),
            ],

            // 2.2 Ancienneté masse (si masse)
            [
                'key' => 'breast_mass_duration',
                'text' => '⏳ Depuis combien de temps avez-vous remarqué cette masse ?',
                'type' => 'select_one',
                'options' => json_encode([
                    ['value' => 'less_1w', 'label' => 'Moins d\'une semaine'],
                    ['value' => '1_4w',    'label' => '1-4 semaines'],
                    ['value' => '1_3m',    'label' => '1-3 mois'],
                    ['value' => 'more_3m', 'label' => 'Plus de 3 mois'],
                ]),
                'bloc_key' => 'symptom_breast',
                'answer_target' => 'evaluation',
                'order' => 8,
                'risk_weight' => 2,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/breast_mass_duration.jpg'),
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'breast_mass', 'value' => true, 'operator' => '=']
                ]),
            ],

            // 3️⃣ Écoulement mamelonnaire
            [
                'key' => 'breast_nipple_discharge',
                'text' => '💧 Avez-vous remarqué un écoulement du mamelon ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_breast',
                'answer_target' => 'evaluation',
                'order' => 9,
                'risk_weight' => 3,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/breast_nipple_discharge.jpg'),
            ],

            // 3.1 Écoulement sanglant (si écoulement)
            [
                'key' => 'breast_nipple_bleeding',
                'text' => '🩸 L’écoulement du mamelon est-il sanglant ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_breast',
                'answer_target' => 'evaluation',
                'order' => 10,
                'risk_weight' => 5,
                'routing_rules' => [
                    [
                        'if' => ['source' => 'answer', 'operator' => '=', 'value' => true],
                        'next' => 'end_session:urgent_breast_nipple_bleeding',
                    ],
                ],
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/breast_nipple_bleeding.jpg'),
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'breast_nipple_discharge', 'value' => true, 'operator' => '=']
                ]),
            ],

            // 4️⃣ Changement du mamelon
            [
                'key' => 'breast_nipple_retraction',
                'text' => '🔄 Le mamelon est-il rentré vers l’intérieur (rétracté) ou a changé récemment ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_breast',
                'answer_target' => 'evaluation',
                'order' => 11,
                'risk_weight' => 4,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/breast_nipple_retraction.jpg'),
            ],
            [
                'key' => 'breast_nipple_eczema',
                'text' => '🧴 Le mamelon présente-t-il des lésions d’aspect eczémateux ou croûteux qui ne guérissent pas ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_breast',
                'answer_target' => 'evaluation',
                'order' => 12,
                'risk_weight' => 5,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/breast_nipple_eczema.jpg'),
            ],

            // 5️⃣ Changement cutané
            [
                'key' => 'breast_skin_orange',
                'text' => '🍊 La peau de votre sein présente-t-elle un aspect “peau d’orange” (granuleuse, épaisse, en relief) ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_breast',
                'answer_target' => 'evaluation',
                'order' => 13,
                'risk_weight' => 5,
                'routing_rules' => [
                    [
                        'if' => ['source' => 'answer', 'operator' => '=', 'value' => true],
                        'next' => 'end_session:urgent_breast_skin_orange',
                    ],
                ],
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/breast_skin_orange.jpg'),
            ],
            [
                'key' => 'breast_skin_change',
                'text' => '🌗 Avez-vous constaté une modification de la peau du sein (rougeur, aspect peau d’orange…) ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_breast',
                'answer_target' => 'evaluation',
                'order' => 14,
                'risk_weight' => 4,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/breast_skin_change.jpg'),
            ],
            [
                'key' => 'breast_ulceration',
                'text' => '🩹 Avez-vous remarqué une plaie (ulcération) sur le sein qui ne cicatrise pas ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_breast',
                'answer_target' => 'evaluation',
                'order' => 15,
                'risk_weight' => 5,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/breast_ulceration.jpg'),
            ],
            [
                'key' => 'breast_asymmetry',
                'text' => '📏 Avez-vous remarqué une différence de taille, de forme ou d’aspect entre vos deux seins apparue récemment ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_breast',
                'answer_target' => 'evaluation',
                'order' => 16,
                'risk_weight' => 4,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/breast_asymmetry.jpg'),
            ],

            // 6️⃣ Masse axillaire
            [
                'key' => 'axillary_ganglion',
                'text' => '🦠 Avez-vous remarqué une boule douloureuse ou non sous l’aisselle ?',
                'type' => 'boolean',
                'bloc_key' => 'symptom_breast',
                'answer_target' => 'evaluation',
                'order' => 17,
                'risk_weight' => 3,
                'media_type' => 'image',
                'media_url' => asset('images/symptoms/axillary_ganglion.jpg'),
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
