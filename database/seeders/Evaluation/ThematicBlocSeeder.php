<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\ThematicBloc;
use App\Domains\Evaluation\Models\QuestionGroup;

/**
 * Parcours "Premier Rendez-vous" — ordre des blocs thématiques.
 *
 * Ordre du parcours :
 *   1. greeting            — Accueil, consentement, raison de la visite
 *   2. profil_identite     — Identité personnelle (prénom, date naissance, genre…)
 *   3. lifestyle           — Mode de vie (tabac, alcool, activité physique, IMC)
 *   4. medical_profile     — Antécédents médicaux (grossesse, ménopause, VIH…)
 *   5. family_history      — Antécédents familiaux (cancer famille)
 *   6. dispatch_intention      — Pivot de routage vers le bon bloc symptomatique
 *   7. symptom_breast          — Évaluation sein        (masqué si raison=dépistage)
 *   8. symptom_cervix          — Évaluation col         (masqué si raison=dépistage, âge ≥ 21)
 *   9. symptom_breast_light    — Dépistage sein allégé  (visible si raison=dépistage)
 *  10. symptom_cervical_light  — Dépistage col allégé   (visible si raison=dépistage, âge ≥ 21)
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
                'key'         => 'profile_identity',
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
                'key'         => 'medical_background',
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
            // 6 — Pivot de routage (dispatch selon raison_visite)
            // Bloc intermédiaire entre la collecte du profil et les symptômes.
            // La question dispatch_intention évalue raison_visite et force le
            // bon bloc symptomatique via forced_bloc dans session.context.
            // ----------------------------------------------------------------
            [
                'key'         => 'dispatch_intention',
                'label'       => 'Orientation',
                'color'       => '#78909C',
                'icon'        => 'git-branch',
                'description' => 'Routage vers le parcours symptomatique adapté à la raison de la visite.',
                'order'       => 6,
                'visible_if'  => null,
                'group_key'   => 'profile_personal',
            ],

            // ----------------------------------------------------------------
            // 7 — Évaluation sein (exclu pour dépistage → symptom_breast_light)
            // ----------------------------------------------------------------
            [
                'key'         => 'symptom_breast',
                'label'       => 'Évaluation sein',
                'color'       => '#E91E8C',
                'icon'        => 'heart',
                'description' => 'Questions sur les symptômes mammaires.',
                'order'       => 7,
                'visible_if'  => [
                    ['source' => 'question', 'key' => 'raison_visite', 'operator' => 'contains', 'value' => 'sein'],
                ],
                'group_key'   => 'symptom_breast',
            ],

            // ----------------------------------------------------------------
            // 8 — Évaluation col utérin (exclu pour dépistage, visible si âge >= 21)
            // ----------------------------------------------------------------
            [
                'key'         => 'symptom_cervix',
                'label'       => 'Évaluation col utérin',
                'color'       => '#AB47BC',
                'icon'        => 'shield',
                'description' => 'Dépistage du cancer du col de l\'utérus.',
                'order'       => 8,
                'visible_if'  => [
                    ['source' => 'question', 'key' => 'raison_visite', 'operator' => 'not_contains', 'value' => 'depistage'],
                    ['source' => 'profile.personal', 'key' => 'birth_date', 'operator' => '!=', 'value' => null],
                    ['source' => 'profile.personal', 'key' => 'birth_date', 'operator' => 'age_gte', 'value' => 21],
                ],
                'group_key'   => 'symptom_cervix',
            ],

            // ----------------------------------------------------------------
            // 9 — Dépistage sein allégé (visible uniquement si raison_visite = depistage)
            // ----------------------------------------------------------------
            [
                'key'         => 'symptom_breast_light',
                'label'       => 'Dépistage sein',
                'color'       => '#F48FB1',
                'icon'        => 'search',
                'description' => 'Questions de dépistage mammaire allégées pour un bilan de routine.',
                'order'       => 9,
                'visible_if'  => [
                    ['source' => 'question', 'key' => 'raison_visite', 'operator' => 'contains', 'value' => 'depistage'],
                ],
                'group_key'   => 'symptom_breast',
            ],

            // ----------------------------------------------------------------
            // 10 — Dépistage col allégé (visible si raison_visite = depistage ET âge >= 21)
            // ----------------------------------------------------------------
            [
                'key'         => 'symptom_cervical_light',
                'label'       => 'Dépistage col utérin',
                'color'       => '#CE93D8',
                'icon'        => 'search',
                'description' => 'Questions de dépistage cervical allégées pour un bilan de routine.',
                'order'       => 10,
                'visible_if'  => [
                    ['source' => 'question', 'key' => 'raison_visite', 'operator' => 'contains', 'value' => 'depistage'],
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
