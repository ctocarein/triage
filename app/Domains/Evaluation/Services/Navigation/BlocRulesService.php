<?php

namespace App\Domains\Evaluation\Services\Navigation;

use App\Domains\Evaluation\Models\ThematicBloc;
use Illuminate\Support\Collection;

/**
 * Évalue les règles de visibilité au niveau des blocs thématiques.
 *
 * Responsabilité unique : déterminer si un bloc entier doit être ignoré
 * en évaluant son champ `visible_if` contre le contexte de session courant.
 *
 * Un bloc sans `visible_if` (ou avec un tableau vide) est toujours visible.
 *
 * Exemple de `visible_if` sur un bloc :
 *   [
 *     {"source": "profile.medical", "key": "genre",  "value": "F"},
 *     {"source": "profile.medical", "key": "age",    "operator": ">=", "value": 21}
 *   ]
 *   → Le bloc ne s'affiche que si la patiente est une femme d'au moins 21 ans.
 */
class BlocRulesService
{
    /** @var Collection<ThematicBloc>|null */
    private ?Collection $cachedBlocs = null;

    public function __construct(
        protected ConditionEvaluator $conditions,
    ) {}

    /**
     * Retourne true si le bloc doit être sauté (non visible).
     *
     * @param  string  $blocKey  Clé du bloc à évaluer
     * @param  array   $answers  Réponses indexées par clé de question (contexte session)
     * @param  array   $profile  ['medical' => [...], 'personal' => [...]]
     */
    public function shouldSkip(string $blocKey, array $answers, array $profile): bool
    {
        $bloc = $this->findBloc($blocKey);

        // Bloc inconnu dans la table → ne pas sauter (tolérance catalogue)
        if (! $bloc) {
            return false;
        }

        $visibleIf = $bloc->visible_if ?? [];

        // Pas de condition → toujours visible
        if (empty($visibleIf)) {
            return false;
        }

        // Condition non satisfaite → bloc invisible → skip
        return ! $this->conditions->isVisible($visibleIf, $answers, $profile);
    }

    /**
     * Retourne les blocs actifs visibles dans le contexte courant, dans l'ordre.
     *
     * Utile pour afficher la progression ou le menu des blocs côté front.
     */
    public function getVisibleBlocs(array $answers, array $profile): Collection
    {
        return $this->getAllBlocs()->filter(
            fn (ThematicBloc $bloc) => ! $this->shouldSkip($bloc->key, $answers, $profile)
        )->values();
    }

    // -------------------------------------------------------------------------
    // Interne
    // -------------------------------------------------------------------------

    private function findBloc(string $blocKey): ?ThematicBloc
    {
        return $this->getAllBlocs()->firstWhere('key', $blocKey);
    }

    private function getAllBlocs(): Collection
    {
        return $this->cachedBlocs ??= ThematicBloc::where('is_active', true)->orderBy('order')->get();
    }
}
