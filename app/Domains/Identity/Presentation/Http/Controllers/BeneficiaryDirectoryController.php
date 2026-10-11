<?php

namespace App\Domains\Identity\Presentation\Http\Controllers;

use App\Domains\Evaluation\Domain\Policies\IdentityEvaluationPolicy;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Annuaire des beneficiaires d'un evaluateur.
 *
 * Sert l'agent de terrain comme l'ambassadeur : tous deux ont besoin de
 * retrouver une personne deja enrolee pour ouvrir une visite de suivi, plutot
 * que de la ressaisir et d'en creer un double.
 */
class BeneficiaryDirectoryController extends Controller
{
    /**
     * GET /agent/beneficiaries
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! $actor instanceof Identity || ! IdentityEvaluationPolicy::isEvaluator($actor)) {
            abort(403, "Acces reserve aux agents de terrain et aux ambassadeurs.");
        }

        $validated = $request->validate([
            'search'   => ['nullable', 'string', 'max:100'],
            'status'   => ['nullable', 'in:in_progress,completed,never,to_follow'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // Perimetre : les organisations ou l'acteur porte un role d'evaluateur.
        // Un role porte ailleurs ne donne acces a rien ici.
        $organizationIds = $actor->memberships()
            ->where('status', 'active')
            ->whereIn('role', IdentityEvaluationPolicy::EVALUATOR_ROLES)
            ->whereNotNull('organization_id')
            ->pluck('organization_id');

        if ($organizationIds->isEmpty()) {
            return response()->json([
                'data' => [],
                'meta' => ['total' => 0, 'per_page' => 0, 'current_page' => 1, 'last_page' => 1],
            ]);
        }

        $query = Identity::query()
            ->where('status', 'active')
            ->whereHas('memberships', function ($q) use ($organizationIds): void {
                $q->where('role', 'Beneficiary')
                    ->where('status', 'active')
                    ->whereIn('organization_id', $organizationIds);
            })
            ->with([
                'personalProfile',
                'credentials' => fn ($q) => $q->where('type', 'phone'),
                'latestEvaluationSession',
            ]);

        if (! empty($validated['search'])) {
            $term = trim($validated['search']);
            $query->where(function ($q) use ($term): void {
                $q->whereHas('credentials', function ($c) use ($term): void {
                    $c->where('type', 'phone')->where('identifier', 'like', "%{$term}%");
                })->orWhereHas('personalProfile', function ($p) use ($term): void {
                    $p->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%");
                });
            });
        }

        if (($validated['status'] ?? null) === 'never') {
            $query->whereDoesntHave('evaluationSessions');
        } elseif (($validated['status'] ?? null) === 'in_progress') {
            $query->whereHas('evaluationSessions', fn ($q) => $q->active());
        } elseif (($validated['status'] ?? null) === 'completed') {
            $query->whereHas('evaluationSessions', fn ($q) => $q->completed());
        } elseif (($validated['status'] ?? null) === 'to_follow') {
            // Fonde sur ses propres evaluations : filtrer sur le risque d'une
            // session conduite par un collegue revelerait ce que mapSession masque.
            $query->whereHas(
                'evaluationSessions',
                fn ($q) => $q->followUpRequiredBy((string) $actor->id),
            );
        }

        // Les plus recemment vues en premier : c'est l'ordre utile sur le terrain,
        // ou l'on rouvre surtout des dossiers du jour.
        $page = $query
            ->orderByDesc(
                EvaluationSession::query()
                    ->selectRaw('MAX(started_at)')
                    ->whereColumn('evaluation_sessions.identity_id', 'identities.id')
            )
            ->orderByDesc('identities.created_at')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => $page->getCollection()
                ->map(fn (Identity $identity) => $this->mapBeneficiary($identity, $actor))
                ->all(),
            'meta' => [
                'total'        => $page->total(),
                'per_page'     => $page->perPage(),
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapBeneficiary(Identity $identity, Identity $actor): array
    {
        $profile = $identity->personalProfile;
        $session = $identity->latestEvaluationSession;

        $name = trim(implode(' ', array_filter([
            $profile?->first_name,
            $profile?->last_name,
        ])));

        return [
            'identity_id'     => (string) $identity->id,
            'name'            => $name !== '' ? $name : null,
            'phone'           => $identity->credentials->first()?->identifier,
            'municipality'    => $profile?->municipality,
            'birth_date'      => $profile?->birth_date,
            'last_evaluation' => $session ? $this->mapSession($session, $actor) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapSession(EvaluationSession $session, Identity $actor): array
    {
        $payload = [
            'session_id'   => $session->public_id,
            'status'       => $session->status,
            'type'         => $session->type,
            'started_at'   => $session->started_at,
            'completed_at' => $session->completed_at,
        ];

        // Le niveau de risque n'est expose qu'a l'evaluateur qui a conduit la
        // session. L'annuaire dit a tous qu'une evaluation existe et quand ;
        // il ne divulgue pas son resultat a un collegue qui n'y etait pas.
        $context = is_array($session->context) ? $session->context : [];
        $conductedByActor = isset($context['agent_id'])
            && (string) $context['agent_id'] === (string) $actor->id;

        if ($conductedByActor) {
            $payload['risk_level'] = $session->risk_level;
        }

        return $payload;
    }
}
