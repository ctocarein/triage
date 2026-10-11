<?php

namespace App\Domains\Identity\Presentation\Http\Controllers;

use App\Domains\Evaluation\Domain\Policies\IdentityEvaluationPolicy;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Profile\Infrastructure\Persistence\Models\PersonalProfile;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ce que l'evaluateur a reellement accompli.
 *
 * Les chiffres se deduisent des evaluations qu'il a conduites, pas d'un
 * compteur maintenu a cote : rien a resynchroniser, et aucun moyen que
 * l'affichage s'ecarte de la realite.
 *
 * Le perimetre est strictement le sien -- context.agent_id -- et non celui de
 * son organisation. Une ambassadrice regarde son travail, pas celui de ses
 * collegues.
 */
class EvaluatorImpactController extends Controller
{
    /**
     * GET /agent/impact
     */
    public function __invoke(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! $actor instanceof Identity || ! IdentityEvaluationPolicy::isEvaluator($actor)) {
            abort(403, "Acces reserve aux agents de terrain et aux ambassadeurs.");
        }

        $conducted = fn (): Builder => EvaluationSession::query()
            ->where('context->agent_id', (string) $actor->id);

        // Femmes distinctes, et non nombre de sessions : revoir la meme personne
        // en visite de suivi ne fait pas deux femmes accompagnees.
        $identityIds = $conducted()->distinct()->pluck('identity_id');

        $municipalities = PersonalProfile::query()
            ->whereIn('identity_id', $identityIds)
            ->whereNotNull('municipality')
            ->where('municipality', '!=', '')
            ->distinct()
            ->orderBy('municipality')
            ->pluck('municipality')
            ->values();

        return response()->json([
            'women_reached'          => $identityIds->count(),
            // Femmes dont au moins une evaluation qu'il a conduite est allee a
            // son terme : une visite en cours ne suffit pas.
            'women_evaluated'        => $conducted()
                ->where('status', 'completed')
                ->distinct()
                ->count('identity_id'),
            'evaluations_completed'  => $conducted()->where('status', 'completed')->count(),
            'evaluations_in_progress' => $conducted()->where('status', 'in_progress')->count(),
            // Le chiffre qui compte cliniquement : combien de femmes ont ete
            // orientees vers une prise en charge rapide.
            'urgent_referrals'       => $conducted()->whereNotNull('context->urgent_referral')->count(),
            // Femmes dont sa derniere evaluation conclut a un risque modere ou
            // eleve : celles qu'il doit revoir ou orienter.
            'women_to_follow'        => EvaluationSession::query()
                ->followUpRequiredBy((string) $actor->id)
                ->distinct()
                ->count('identity_id'),
            'municipalities_covered' => $municipalities->count(),
            'municipalities'         => $municipalities,
            'active_since'           => $conducted()->min('started_at'),
        ]);
    }
}
