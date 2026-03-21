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
        // Bloc `medical_background` (order 9)
        // Questions générales : maladies chroniques, traitements, cancer.
        // Les questions gynéco ont été déplacées dans GynecologicBackgroundSeeder.
        // Les questions dépistage ont été déplacées dans ScreeningHistorySeeder.
        // ----------------------------------------------------------------
        $medicalBackgroundQuestions = [
            [
                'key'            => 'other_diseases',
                'text'           => 'Avez-vous des maladies chroniques ou conditions médicales importantes ? (diabète, hypertension…)',
                'type'           => 'text',
                'bloc_key'       => 'medical_background',
                'answer_target'  => 'medical_profile',
                'order'          => 1,
                'is_entry_point' => true,
            ],
            [
                'key'           => 'current_medications',
                'text'          => 'Prenez-vous actuellement des médicaments sur ordonnance ? Si oui, lesquels ?',
                'type'          => 'text',
                'bloc_key'      => 'medical_background',
                'answer_target' => 'medical_profile',
                'order'         => 2,
            ],
            [
                'key'           => 'blood_type',
                'text'          => 'Quel est votre groupe sanguin ?',
                'type'          => 'select_one',
                'bloc_key'      => 'medical_background',
                'answer_target' => 'medical_profile',
                'order'         => 3,
                'options'       => json_encode([
                    ['value' => 'A+',      'label' => 'A+'],
                    ['value' => 'A-',      'label' => 'A-'],
                    ['value' => 'B+',      'label' => 'B+'],
                    ['value' => 'B-',      'label' => 'B-'],
                    ['value' => 'AB+',     'label' => 'AB+'],
                    ['value' => 'AB-',     'label' => 'AB-'],
                    ['value' => 'O+',      'label' => 'O+'],
                    ['value' => 'O-',      'label' => 'O-'],
                    ['value' => 'unknown', 'label' => 'Je ne sais pas'],
                ]),
            ],
            [
                'key'           => 'allergies',
                'text'          => 'Avez-vous des allergies connues ? (précisez si oui)',
                'type'          => 'text',
                'bloc_key'      => 'medical_background',
                'answer_target' => 'medical_profile',
                'order'         => 4,
            ],
            [
                'key'           => 'exposed_to_smoke',
                'text'          => 'Êtes-vous exposée à la fumée (tabac passif, feu de bois, pollution…) ?',
                'type'          => 'boolean',
                'bloc_key'      => 'medical_background',
                'answer_target' => 'medical_profile',
                'order'         => 5,
            ],
            [
                'key'           => 'exposure_type',
                'text'          => 'Quel type d\'exposition ? (tabac, feu, pollution, autre…)',
                'type'          => 'text',
                'bloc_key'      => 'medical_background',
                'answer_target' => 'medical_profile',
                'order'         => 6,
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
                'order'         => 7,
                // Pas de routing forcé : le flux naturel continue vers gynecologic_background (10).
            ],
            [
                'key'           => 'cancer_details',
                'text'          => 'Quel type de cancer avez-vous eu ?',
                'type'          => 'text',
                'bloc_key'      => 'medical_background',
                'answer_target' => 'medical_profile',
                'order'         => 8,
                'visible_if'    => json_encode([
                    ['source' => 'question', 'key' => 'has_cancer', 'operator' => '=', 'value' => true],
                ]),
                // Pas de routing forcé : le flux naturel continue vers gynecologic_background (10).
            ],
        ];

        // ----------------------------------------------------------------
        // Bloc `family_history` (order 11)
        // ----------------------------------------------------------------
        $familyQuestions = [
            [
                'key'           => 'family_cancer_history',
                'text'          => 'Avez-vous des antécédents familiaux de cancer (mère, sœur, tante…) ?',
                'type'          => 'boolean',
                'bloc_key'      => 'family_history',
                'answer_target' => 'medical_profile',
                'order'         => 1,
                // Pas de routing forcé : le flux naturel continue vers lifestyle (12).
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
                // Pas de routing forcé : le flux naturel continue vers lifestyle (12).
            ],
        ];

        $validator->assertUniqueOrderPerBloc($medicalBackgroundQuestions, static::class . '::medical');
        foreach ($medicalBackgroundQuestions as $q) {
            $q = $validator->normalize($q);
            $validator->assertValid($q);
            DynamicQuestionTemplate::updateOrCreate(
                ['key' => $q['key']],
                array_merge($q, ['question_group_id' => $medGroup->id])
            );
        }

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
