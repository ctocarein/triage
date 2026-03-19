<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\ThematicBloc;
use App\Domains\Evaluation\Models\QuestionGroup;

/**
 * Parcours "Premier Rendez-vous" — ordre des blocs thématiques.
 *
 * Ordre du parcours :
 *   1. greeting       — Accueil, consentement, raison de la visite
 *   2. profil_identite — Identité personnelle (prénom, date naissance, genre…)
 *   3. lifestyle       — Mode de vie (tabac, alcool, activité physique, IMC)
 *   4. medical_profile — Antécédents médicaux (grossesse, ménopause, VIH…)
 *   5. family_history  — Antécédents familiaux (cancer famille)
 *   6. symptom_breast      — Évaluation sein (toujours visible)
 *   7. symptom_breast_lump — Masse palpable (toujours visible, questions filtrées par visible_if)
 *   8. symptom_cervix  — Évaluation col (visible si genre=F ET age≥21)
 */
class ThematicBlocSeeder extends Seeder
{
    public function run(): void
    {
        $blocs = [
            // ----------------------------------------------------------------
            // 0 — Bloc de sortie : consentement refusé (hors parcours principal)
            // ----------------------------------------------------------------
            [
                'key'         => 'consent_declined',
                'label'       => 'Consentement refusé',
                'color'       => '#9E9E9E',
                'icon'        => 'x-circle',
                'description' => 'Bloc affiché quand la patiente refuse le consentement.',
                // Hors parcours principal — order=99 pour ne jamais remonter dans le flux normal.
                // Ce bloc n'est atteint que par routing (forced_bloc) quand le consentement est refusé.
                'order'       => 99,
                'visible_if'  => [
                    ['source' => 'question', 'key' => 'explicit_consent', 'operator' => '=', 'value' => 'no'],
                ],
                'group_key'   => 'profile_personal',
            ],

            // ----------------------------------------------------------------
            // 1 — Accueil
            // ----------------------------------------------------------------
            [
                'key'         => 'greeting',
                'label'       => 'Accueil',
                'color'       => '#7E57C2',
                'icon'        => 'home',
                'description' => 'Message de bienvenue, confidentialité et consentement.',
                'order'       => 1,
                'visible_if'  => null,
                'group_key'   => 'profile_personal',
            ],

            // ----------------------------------------------------------------
            // 2 — Profil personnel
            // ----------------------------------------------------------------
            [
                'key'         => 'profil_identite',
                'label'       => 'Votre profil',
                'color'       => '#F8A734',
                'icon'        => 'user',
                'description' => 'Informations sur votre identité et situation personnelle.',
                'order'       => 2,
                'visible_if'  => null,
                'group_key'   => 'profil_identite',
            ],

            // ----------------------------------------------------------------
            // 3 — Mode de vie
            // ----------------------------------------------------------------
            [
                'key'         => 'lifestyle',
                'label'       => 'Mode de vie',
                'color'       => '#4CAF50',
                'icon'        => 'activity',
                'description' => 'Habitudes de vie pouvant influencer votre santé.',
                'order'       => 3,
                'visible_if'  => null,
                'group_key'   => 'profil_socio',
            ],

            // ----------------------------------------------------------------
            // 4 — Antécédents médicaux
            // ----------------------------------------------------------------
            [
                'key'         => 'medical_profile',
                'label'       => 'Antécédents médicaux',
                'color'       => '#4DB6AC',
                'icon'        => 'clipboard',
                'description' => 'Facteurs de risque médicaux et antécédents personnels.',
                'order'       => 4,
                'visible_if'  => null,
                'group_key'   => 'medical_profile',
            ],

            // ----------------------------------------------------------------
            // 5 — Antécédents familiaux
            // ----------------------------------------------------------------
            [
                'key'         => 'family_history',
                'label'       => 'Antécédents familiaux',
                'color'       => '#90CAF9',
                'icon'        => 'users',
                'description' => 'Antécédents médicaux dans votre famille proche.',
                'order'       => 5,
                'visible_if'  => null,
                'group_key'   => 'profil_familial',
            ],

            // ----------------------------------------------------------------
            // 6 — Évaluation sein (toujours visible)
            // ----------------------------------------------------------------
            [
                'key'         => 'symptom_breast',
                'label'       => 'Évaluation sein',
                'color'       => '#E91E8C',
                'icon'        => 'heart',
                'description' => 'Questions sur les symptômes mammaires.',
                'order'       => 6,
                'visible_if'  => null,
                'group_key'   => 'symptom_breast',
            ],

            // ----------------------------------------------------------------
            // 7 — Évaluation col utérin (visible si birth_date connue et âge >= 21)
            // Application 100% féminine — genre F par défaut.
            // ----------------------------------------------------------------
            [
                'key'         => 'symptom_cervix',
                'label'       => 'Évaluation col utérin',
                'color'       => '#AB47BC',
                'icon'        => 'shield',
                'description' => 'Dépistage du cancer du col de l\'utérus.',
                'order'       => 7,
                'visible_if'  => [
                    ['source' => 'profile.personal', 'key' => 'birth_date', 'operator' => '!=', 'value' => null],
                    ['source' => 'profile.personal', 'key' => 'birth_date', 'operator' => 'age_gte', 'value' => 21],
                ],
                'group_key'   => 'symptom_cervix',
            ],
        ];

        foreach ($blocs as $bloc) {
            $groupId = QuestionGroup::where('key', $bloc['group_key'])->value('id');

            ThematicBloc::updateOrCreate(
                ['key' => $bloc['key']],
                [
                    'label'             => $bloc['label'],
                    'color'             => $bloc['color'],
                    'icon'              => $bloc['icon'],
                    'description'       => $bloc['description'],
                    'order'             => $bloc['order'],
                    'visible_if'        => $bloc['visible_if'],
                    'is_active'         => true,
                    'question_group_id' => $groupId,
                ]
            );
        }
    }
}
