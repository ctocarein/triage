<?php

namespace App\Domains\Evaluation\Services\Navigation;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\EvaluationSession;
use Illuminate\Support\Collection;

/**
 * Ordonnancement adaptatif des blocs de questions.
 *
 * Level 1 — Tri des blocs par potentiel de contribution décroissant.
 *           Les blocs dont les questions non répondues ont le plus fort poids
 *           de risque cumulé passent en premier.
 *
 * Level 2 — Sortie anticipée (early exit).
 *           Si le score courant + le potentiel restant maximal ne peut pas
 *           changer de zone de risque, la session est considérée terminée.
 */
class AdaptiveSequencer
{
    /**
     * Réordonne les blocs par potentiel de contribution décroissant.
     *
     * Les blocs déjà visités sont exclus ; un forced_bloc éventuellement
     * positionné en tête par QuestionSequencer reste en place (il n'est
     * pas passé ici).
     *
     * @param  Collection<int, string>              $blocs          Clés de blocs dans l'ordre courant
     * @param  Collection<int, DynamicQuestionTemplate> $allTemplates
     * @param  array<int>                           $answeredIds    IDs de questions déjà répondues
     * @param  array<string, mixed>                 $answers        Réponses indexées par question.key
     * @param  array<string, array>                 $profile        Profil patient (medical/personal)
     * @return Collection<int, string>              Blocs triés
     */
    public function prioritizeBlocs(
        Collection $blocs,
        Collection $allTemplates,
        array $answeredIds,
        array $answers,
        array $profile,
        QuestionSequencer $flow,
    ): Collection {
        $withPotential    = [];
        $withoutPotential = [];

        foreach ($blocs as $bloc) {
            $potential = $this->blocPotential($bloc, $allTemplates, $answeredIds, $answers, $profile, $flow);
            if ($potential > 0) {
                $withPotential[] = ['key' => $bloc, 'potential' => $potential];
            } else {
                $withoutPotential[] = $bloc;
            }
        }

        $sorted = collect($withPotential)->sortByDesc('potential')->pluck('key');

        // Les blocs sans potentiel (accueil, profil, etc.) conservent
        // leur ordre original et passent avant les blocs à risque triés.
        return collect($withoutPotential)->merge($sorted)->values();
    }

    /**
     * Détermine si le reste du questionnaire peut encore changer de zone de risque.
     *
     * Retourne false (= on peut terminer) si :
     *   score_actuel + potentiel_restant_max  →  même zone que score_actuel seul
     *
     * @param  float                                $currentScore   Score courant (session->score)
     * @param  Collection<int, string>              $remainingBlocs Blocs non encore visités
     * @param  Collection<int, DynamicQuestionTemplate> $allTemplates
     * @param  array<int>                           $answeredIds
     * @param  array<string, mixed>                 $answers
     * @param  array<string, array>                 $profile
     */
    public function canChangeRiskLevel(
        float $currentScore,
        Collection $remainingBlocs,
        Collection $allTemplates,
        array $answeredIds,
        array $answers,
        array $profile,
        QuestionSequencer $flow,
    ): bool {
        // Mode conservatif : on inclut TOUTES les questions non répondues du bloc,
        // même celles masquées par visible_if. Des conditions non encore évaluées
        // pourraient devenir vraies au fil des réponses ; exclure ces questions
        // risque de sous-estimer le potentiel et de déclencher un early exit prématuré.
        $remainingPotential = $remainingBlocs->sum(
            fn (string $bloc) => $this->blocPotential($bloc, $allTemplates, $answeredIds, $answers, $profile, $flow, conservative: true)
        );

        if ($remainingPotential <= 0) {
            return false;
        }

        $levels      = config('risk-level.levels', []);
        $currentZone = $this->resolveZone($currentScore, $levels);
        $maxZone     = $this->resolveZone($currentScore + $remainingPotential, $levels);

        return $currentZone !== $maxZone;
    }

    // -------------------------------------------------------------------------

    /**
     * Potentiel de contribution d'un bloc = somme des risk_weight des questions non répondues.
     *
     * @param  bool  $conservative  Si true, inclut les questions masquées par visible_if.
     *                              Utiliser true pour les décisions d'early exit (borne supérieure sûre) ;
     *                              false pour le tri des blocs (estimation précise).
     */
    private function blocPotential(
        string $bloc,
        Collection $allTemplates,
        array $answeredIds,
        array $answers,
        array $profile,
        QuestionSequencer $flow,
        bool $conservative = false,
    ): float {
        $candidates = $allTemplates
            ->where('bloc_key', $bloc)
            ->filter(fn (DynamicQuestionTemplate $q) => ! in_array($q->id, $answeredIds, true));

        if (! $conservative) {
            $candidates = $candidates->filter(
                fn (DynamicQuestionTemplate $q) => $flow->shouldBeVisible($q, $answers, $profile)
            );
        }

        return (float) $candidates->sum(fn (DynamicQuestionTemplate $q) => max(0, (float) ($q->risk_weight ?? 0)));
    }

    /**
     * Retourne le nom de la zone de risque pour un score donné.
     */
    private function resolveZone(float $score, array $levels): string
    {
        foreach ($levels as $zone) {
            if ($score >= $zone['min'] && $score <= $zone['max']) {
                return $zone['name'];
            }
        }

        return 'unknown';
    }
}
