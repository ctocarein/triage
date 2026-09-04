<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Services\Catalog\QuestionTemplateContractValidator;

/**
 * Questions du bloc "Mode de vie" (lifestyle).
 *
 * Toutes les réponses ciblent `medical_profile` pour être disponibles
 * dans les évaluations sein et col sans répétition.
 */
class LifestyleQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $validator = new QuestionTemplateContractValidator();
        $group     = QuestionGroup::where('key', 'profil_socio')->firstOrFail();

        $questions = [
            // ----------------------------------------------------------------
            // Tabac
            // ----------------------------------------------------------------
            [
                'key'           => 'tabac',
                'text'          => 'Fumez-vous, ou avez-vous déjà fumé ?',
                'type'          => 'boolean',
                'bloc_key'      => 'lifestyle',
                'answer_target' => 'medical_profile',
                'order'         => 1,
                'risk_weight'   => 2,
            ],
            [
                'key'           => 'tabac_paquets_annee',
                'text'          => 'Combien de paquets par an fumez-vous (ou fumiez-vous) en moyenne ?',
                'type'          => 'number',
                'bloc_key'      => 'lifestyle',
                'answer_target' => 'medical_profile',
                'order'         => 2,
                'risk_weight'   => 1,
                'visible_if'    => json_encode([
                    ['source' => 'question', 'key' => 'tabac', 'operator' => '=', 'value' => true],
                ]),
            ],

            // ----------------------------------------------------------------
            // Alcool
            // ----------------------------------------------------------------
            [
                'key'           => 'alcool',
                'text'          => 'Consommez-vous de l\'alcool ?',
                'type'          => 'select_one',
                'bloc_key'      => 'lifestyle',
                'answer_target' => 'medical_profile',
                'order'         => 3,
                'risk_weight'   => 1,
                'options'       => json_encode([
                    ['value' => 'jamais',      'label' => 'Jamais'],
                    ['value' => 'occasionnel', 'label' => 'Occasionnellement (moins d\'1 fois/semaine)'],
                    ['value' => 'regulier',    'label' => 'Régulièrement (1 à 3 fois/semaine)'],
                    ['value' => 'quotidien',   'label' => 'Tous les jours'],
                ]),
            ],

            // ----------------------------------------------------------------
            // Activité physique
            // ----------------------------------------------------------------
            [
                'key'           => 'activite_physique',
                'text'          => 'Quel est votre niveau d\'activité physique habituel ?',
                'type'          => 'select_one',
                'bloc_key'      => 'lifestyle',
                'answer_target' => 'medical_profile',
                'order'         => 4,
                'risk_weight'   => 0,
                'options'       => json_encode([
                    ['value' => 'sedentaire', 'label' => 'Sédentaire (peu ou pas d\'activité)'],
                    ['value' => 'moderee',    'label' => 'Modérée (marche, activité légère < 3h/semaine)'],
                    ['value' => 'active',     'label' => 'Active (sport régulier ≥ 3h/semaine)'],
                ]),
            ],

            // ----------------------------------------------------------------
            // Stress chronique (avant IMC pour terminer lifestyle avant le saut vers symptom_breast)
            // ----------------------------------------------------------------
            [
                'key'           => 'stress_chronique',
                'text'          => 'Vous sentez-vous souvent stressée ou anxieuse de manière prolongée ?',
                'type'          => 'boolean',
                'bloc_key'      => 'lifestyle',
                'answer_target' => 'medical_profile',
                'order'         => 5,
                'risk_weight'   => 0,
            ],

            // ----------------------------------------------------------------
            // IMC (taille + poids → calculé côté front, ou saisi directement)
            // Placés en fin de bloc pour que lifestyle soit complet avant le
            // passage au bloc suivant. (L'ancien commentaire evoquait un routage
            // queue:breast_palpation sur taille_cm : il n'a jamais existe, et la
            // question visee a ete retiree du catalogue.)
            // ----------------------------------------------------------------
            [
                'key'           => 'poids_kg',
                'text'          => 'Quel est votre poids actuel (en kg) ?',
                'type'          => 'number',
                'bloc_key'      => 'lifestyle',
                'answer_target' => 'medical_profile',
                'order'         => 6,
                'risk_weight'   => 0,
                'options'       => null,
                'is_required'   => false,
                'placeholder'   => 'Ex: 70',
            ],
            [
                'key'           => 'taille_cm',
                'text'          => 'Quelle est votre taille (en cm) ?',
                'type'          => 'number',
                'bloc_key'      => 'lifestyle',
                'answer_target' => 'medical_profile',
                'order'         => 7,
                'risk_weight'   => 0,
                'options'       => null,
                'is_required'   => false,
                'placeholder'   => 'Ex: 170',
                // Pas de routing forcé : le flux naturel continue vers screening_history (13).
            ],
        ];

        $validator->assertUniqueOrderPerBloc($questions, static::class);

        foreach ($questions as $q) {
            $q['question_group_id'] = $group->id;
            $q = $validator->normalize($q);
            $validator->assertValid($q);
            DynamicQuestionTemplate::updateOrCreate(['key' => $q['key']], $q);
        }
    }
}
