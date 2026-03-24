<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\ThematicBloc;
use App\Domains\Evaluation\Models\QuestionGroup;

/**
 * Parcours "Premier Rendez-vous" — ordre des blocs thématiques.
 *
 * Ordre optimisé :
 *   1.  greeting              — Accueil, confidentialité, consentement, motif de visite
 *   2.  patient_context       — Âge et commune (filtrage précoce, sortie mineure)
 *   3.  emergency_screen      — Signaux d'alarme immédiats (orientation urgente si positif)
 *   4.  dispatch_intention    — Pivot de routage vers le bon bloc symptomatique
 *   5.  symptom_breast        — Évaluation sein        (masqué si raison=dépistage)
 *   6.  symptom_cervix        — Évaluation col         (masqué si raison=dépistage, âge ≥ 21)
 *   7.  symptom_breast_light  — Dépistage sein allégé  (visible si raison=dépistage)
 *   8.  symptom_cervical_light— Dépistage col allégé   (visible si raison=dépistage, âge ≥ 21)
 *   9.  medical_background    — Antécédents médicaux généraux
 *  10.  gynecologic_background — Antécédents gynécologiques et obstétricaux
 *  11.  family_history        — Antécédents familiaux de cancer
 *  12.  lifestyle             — Habitudes de vie (tabac, alcool, activité physique, IMC)
 *  13.  screening_history     — Historique de dépistage
 *  14.  patient_demographics  — Données démographiques complètes (prénom, CMU…)
 *  99.  consent_declined      — Bloc de sortie : consentement refusé / mineur
 */
class ThematicBlocSeeder extends Seeder
{
    public function run(): void
    {
        $blocs = [
            // ----------------------------------------------------------------
            // 99 — Bloc de sortie : consentement refusé ou mineur (hors parcours)
            // ----------------------------------------------------------------
            [
                'key'                => 'consent_declined',
                'label'              => 'Consentement refusé',
                'color'              => '#9E9E9E',
                'icon'               => 'x-circle',
                'description'        => 'Bloc affiché quand la patiente refuse le consentement ou est mineure.',
                'order'              => 99,
                'intention'          => 'crisis',
                'priority'           => 100,
                'recurrence_policy'  => 'once',
                'trigger_conditions' => null,
                'visible_if'         => [
                    ['source' => 'question', 'key' => 'explicit_consent', 'operator' => '=', 'value' => 'no'],
                ],
                'group_key'          => 'profile_personal',
            ],

            // ----------------------------------------------------------------
            // 1 — Accueil
            // ----------------------------------------------------------------
            [
                'key'                => 'greeting',
                'label'              => 'Accueil',
                'color'              => '#7E57C2',
                'icon'               => 'home',
                'description'        => 'Message de bienvenue, confidentialité, consentement et motif de visite.',
                'order'              => 1,
                'intention'          => 'assessment',
                'priority'           => 0,
                'recurrence_policy'  => 'once',
                'trigger_conditions' => null,
                'visible_if'         => null,
                'group_key'          => 'profile_personal',
            ],

            // ----------------------------------------------------------------
            // 2 — Contexte patient (âge + commune)
            // ----------------------------------------------------------------
            [
                'key'                => 'patient_context',
                'label'              => 'Votre situation',
                'color'              => '#F8A734',
                'icon'               => 'user',
                'description'        => 'Date de naissance et commune pour filtrer le parcours.',
                'order'              => 2,
                'intention'          => 'preparation',
                'priority'           => 0,
                'recurrence_policy'  => 'once',
                'trigger_conditions' => null,
                'visible_if'         => null,
                'group_key'          => 'profil_identite',
            ],

            // ----------------------------------------------------------------
            // 3 — Écran d'urgence
            // ----------------------------------------------------------------
            [
                'key'                => 'emergency_screen',
                'label'              => 'Signaux d\'alarme',
                'color'              => '#F44336',
                'icon'               => 'alert-triangle',
                'description'        => 'Détection rapide des signes nécessitant une consultation urgente.',
                'order'              => 3,
                'intention'          => 'crisis',
                'priority'           => 90,
                'recurrence_policy'  => 'once',
                'trigger_conditions' => null,
                'visible_if'         => null,
                'group_key'          => 'profile_personal',
            ],

            // ----------------------------------------------------------------
            // 4 — Pivot de routage symptomatique
            // ----------------------------------------------------------------
            [
                'key'                => 'dispatch_intention',
                'label'              => 'Orientation',
                'color'              => '#78909C',
                'icon'               => 'git-branch',
                'description'        => 'Routage vers le parcours symptomatique adapté à la raison de la visite.',
                'order'              => 4,
                'intention'          => 'triage',
                'priority'           => 0,
                'recurrence_policy'  => 'once',
                'trigger_conditions' => null,
                'visible_if'         => null,
                'group_key'          => 'profile_personal',
            ],

            // ----------------------------------------------------------------
            // 5 — Évaluation sein (symptômes déclarés)
            // ----------------------------------------------------------------
            [
                'key'                => 'symptom_breast',
                'label'              => 'Évaluation sein',
                'color'              => '#E91E8C',
                'icon'               => 'heart',
                'description'        => 'Questions sur les symptômes mammaires.',
                'order'              => 5,
                'intention'          => 'assessment',
                'priority'           => 0,
                'recurrence_policy'  => 'once',
                'trigger_conditions' => null,
                'visible_if'         => [
                    ['source' => 'question', 'key' => 'raison_visite', 'operator' => 'contains', 'value' => 'sein'],
                ],
                'group_key'          => 'symptom_breast',
            ],

            // ----------------------------------------------------------------
            // 6 — Évaluation col utérin (symptômes déclarés, âge ≥ 21)
            // ----------------------------------------------------------------
            [
                'key'                => 'symptom_cervix',
                'label'              => 'Évaluation col utérin',
                'color'              => '#AB47BC',
                'icon'               => 'shield',
                'description'        => 'Dépistage du cancer du col de l\'utérus.',
                'order'              => 6,
                'intention'          => 'assessment',
                'priority'           => 0,
                'recurrence_policy'  => 'once',
                'trigger_conditions' => null,
                'visible_if'         => [
                    ['source' => 'question', 'key' => 'raison_visite', 'operator' => 'not_contains', 'value' => 'depistage'],
                    ['source' => 'profile.personal', 'key' => 'birth_date', 'operator' => '!=', 'value' => null],
                    ['source' => 'profile.personal', 'key' => 'birth_date', 'operator' => 'age_gte', 'value' => 21],
                ],
                'group_key'          => 'symptom_cervix',
            ],

            // ----------------------------------------------------------------
            // 7 — Dépistage sein allégé
            // ----------------------------------------------------------------
            [
                'key'                => 'symptom_breast_light',
                'label'              => 'Dépistage sein',
                'color'              => '#F48FB1',
                'icon'               => 'search',
                'description'        => 'Questions de dépistage mammaire allégées pour un bilan de routine.',
                'order'              => 7,
                'intention'          => 'screening',
                'priority'           => 0,
                'recurrence_policy'  => 'once',
                'trigger_conditions' => null,
                'visible_if'         => [
                    ['source' => 'question', 'key' => 'raison_visite', 'operator' => 'contains', 'value' => 'depistage'],
                ],
                'group_key'          => 'symptom_breast',
            ],

            // ----------------------------------------------------------------
            // 8 — Dépistage col allégé (âge ≥ 21)
            // ----------------------------------------------------------------
            [
                'key'                => 'symptom_cervical_light',
                'label'              => 'Dépistage col utérin',
                'color'              => '#CE93D8',
                'icon'               => 'search',
                'description'        => 'Questions de dépistage cervical allégées pour un bilan de routine.',
                'order'              => 8,
                'intention'          => 'screening',
                'priority'           => 0,
                'recurrence_policy'  => 'once',
                'trigger_conditions' => null,
                'visible_if'         => [
                    ['source' => 'question', 'key' => 'raison_visite', 'operator' => 'contains', 'value' => 'depistage'],
                    ['source' => 'profile.personal', 'key' => 'birth_date', 'operator' => '!=', 'value' => null],
                    ['source' => 'profile.personal', 'key' => 'birth_date', 'operator' => 'age_gte', 'value' => 21],
                ],
                'group_key'          => 'symptom_cervix',
            ],

            // ----------------------------------------------------------------
            // 9 — Antécédents médicaux généraux
            // ----------------------------------------------------------------
            [
                'key'                => 'medical_background',
                'label'              => 'Antécédents médicaux',
                'color'              => '#4DB6AC',
                'icon'               => 'clipboard',
                'description'        => 'Maladies chroniques, traitements, groupe sanguin, allergies, cancer.',
                'order'              => 9,
                'intention'          => 'preparation',
                'priority'           => 0,
                'recurrence_policy'  => 'once',
                'trigger_conditions' => null,
                'visible_if'         => null,
                'group_key'          => 'medical_profile',
            ],

            // ----------------------------------------------------------------
            // 10 — Antécédents gynécologiques et obstétricaux
            // ----------------------------------------------------------------
            [
                'key'                => 'gynecologic_background',
                'label'              => 'Antécédents gynécologiques',
                'color'              => '#F06292',
                'icon'               => 'heart',
                'description'        => 'Grossesse, allaitement, ménopause, nombre de grossesses et enfants.',
                'order'              => 10,
                'intention'          => 'preparation',
                'priority'           => 0,
                'recurrence_policy'  => 'once',
                'trigger_conditions' => null,
                'visible_if'         => null,
                'group_key'          => 'profil_repro',
            ],

            // ----------------------------------------------------------------
            // 11 — Antécédents familiaux
            // ----------------------------------------------------------------
            [
                'key'                => 'family_history',
                'label'              => 'Antécédents familiaux',
                'color'              => '#90CAF9',
                'icon'               => 'users',
                'description'        => 'Antécédents médicaux dans votre famille proche.',
                'order'              => 11,
                'intention'          => 'preparation',
                'priority'           => 0,
                'recurrence_policy'  => 'once',
                'trigger_conditions' => null,
                'visible_if'         => null,
                'group_key'          => 'profil_familial',
            ],

            // ----------------------------------------------------------------
            // 12 — Habitudes de vie
            // ----------------------------------------------------------------
            [
                'key'                => 'lifestyle',
                'label'              => 'Mode de vie',
                'color'              => '#4CAF50',
                'icon'               => 'activity',
                'description'        => 'Tabac, alcool, activité physique, poids et taille.',
                'order'              => 12,
                'intention'          => 'preparation',
                'priority'           => 0,
                'recurrence_policy'  => 'once',
                'trigger_conditions' => null,
                'visible_if'         => null,
                'group_key'          => 'profil_socio',
            ],

            // ----------------------------------------------------------------
            // 13 — Historique de dépistage
            // ----------------------------------------------------------------
            [
                'key'                => 'screening_history',
                'label'              => 'Historique de dépistage',
                'color'              => '#29B6F6',
                'icon'               => 'calendar',
                'description'        => 'Date et résultat du dernier dépistage (frottis, mammographie…).',
                'order'              => 13,
                'intention'          => 'preparation',
                'priority'           => 0,
                'recurrence_policy'  => 'once',
                'trigger_conditions' => null,
                'visible_if'         => null,
                'group_key'          => 'profil_sante',
            ],

            // ----------------------------------------------------------------
            // 14 — Données démographiques complètes
            // ----------------------------------------------------------------
            [
                'key'                => 'patient_demographics',
                'label'              => 'Votre profil complet',
                'color'              => '#FF8A65',
                'icon'               => 'user-check',
                'description'        => 'Prénom, couverture maladie et informations complémentaires.',
                'order'              => 14,
                'intention'          => 'preparation',
                'priority'           => 0,
                'recurrence_policy'  => 'once',
                'trigger_conditions' => null,
                'visible_if'         => null,
                'group_key'          => 'profil_identite',
            ],
        ];

        foreach ($blocs as $bloc) {
            $groupId = QuestionGroup::where('key', $bloc['group_key'])->value('id');

            ThematicBloc::updateOrCreate(
                ['key' => $bloc['key']],
                [
                    'label'              => $bloc['label'],
                    'color'              => $bloc['color'],
                    'icon'               => $bloc['icon'],
                    'description'        => $bloc['description'],
                    'order'              => $bloc['order'],
                    'intention'          => $bloc['intention'],
                    'priority'           => $bloc['priority'],
                    'recurrence_policy'  => $bloc['recurrence_policy'],
                    'trigger_conditions' => $bloc['trigger_conditions'],
                    'visible_if'         => $bloc['visible_if'],
                    'is_active'          => true,
                    'question_group_id'  => $groupId,
                ]
            );
        }

        // Désactiver les anciens blocs qui n'existent plus dans le nouveau parcours.
        ThematicBloc::whereIn('key', ['profile_identity'])->update(['is_active' => false]);
    }
}
