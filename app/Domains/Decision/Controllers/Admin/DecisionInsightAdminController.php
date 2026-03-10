<?php

namespace App\Domains\Decision\Controllers\Admin;

use App\Domains\Decision\Models\DecisionInsight;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DecisionInsightAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor   = $request->user();
        $isAdmin = $actor instanceof Identity && $actor->hasMembershipRole('PlatformAdmin');

        $identityIds = null;
        if (! $isAdmin && $actor instanceof Identity) {
            $orgIds = $actor->memberships()
                ->where('role', 'OrgManager')
                ->pluck('organization_id')
                ->filter()
                ->all();

            $identityIds = Identity::whereHas('memberships', fn ($q) =>
                $q->where('role', 'Beneficiary')->whereIn('organization_id', $orgIds)
            )->pluck('id')->all();
        }

        $query = DecisionInsight::query()
            ->with([
                'session:id,public_id,status,started_at,completed_at',
                'identity:id,kind,status',
            ])
            ->orderByDesc('created_at');

        if ($identityIds !== null) {
            $query->whereIn('identity_id', $identityIds);
        }

        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->input('risk_level'));
        }

        if ($request->filled('requires_validation')) {
            $query->where('requires_human_validation', (bool) $request->input('requires_validation'));
        }

        $paginated = $query->paginate($request->integer('per_page', 20));

        return response()->json($paginated);
    }

    public function stats(Request $request): JsonResponse
    {
        $actor   = $request->user();
        $isAdmin = $actor instanceof Identity && $actor->hasMembershipRole('PlatformAdmin');

        $identityIds = null;
        if (! $isAdmin && $actor instanceof Identity) {
            $orgIds = $actor->memberships()
                ->where('role', 'OrgManager')
                ->pluck('organization_id')
                ->filter()
                ->all();

            $identityIds = Identity::whereHas('memberships', fn ($q) =>
                $q->where('role', 'Beneficiary')->whereIn('organization_id', $orgIds)
            )->pluck('id')->all();
        }

        $baseQuery = DecisionInsight::query();
        if ($identityIds !== null) {
            $baseQuery->whereIn('identity_id', $identityIds);
        }

        $riskDistribution = (clone $baseQuery)
            ->whereNotNull('risk_level')
            ->select('risk_level', DB::raw('count(*) as count'), DB::raw('avg(risk_score) as avg_score'))
            ->groupBy('risk_level')
            ->get()
            ->map(fn ($r) => [
                'risk_level' => $r->risk_level,
                'count'      => (int) $r->count,
                'avg_score'  => round((float) $r->avg_score, 1),
            ])
            ->values();

        $suggestedActions = (clone $baseQuery)
            ->whereNotNull('suggested_action')
            ->select('suggested_action', DB::raw('count(*) as count'))
            ->groupBy('suggested_action')
            ->get()
            ->pluck('count', 'suggested_action')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        return response()->json([
            'total'                     => (clone $baseQuery)->count(),
            'requires_validation'       => (clone $baseQuery)->where('requires_human_validation', true)->count(),
            'avg_confidence'            => round((float) ((clone $baseQuery)->avg('confidence_score') ?? 0), 2),
            'avg_risk_score'            => round((float) ((clone $baseQuery)->avg('risk_score') ?? 0), 1),
            'risk_distribution'         => $riskDistribution,
            'suggested_actions'         => $suggestedActions,
        ]);
    }
}
