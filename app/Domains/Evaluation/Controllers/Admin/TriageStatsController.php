<?php

namespace App\Domains\Evaluation\Controllers\Admin;

use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Profile\Infrastructure\Persistence\Models\MedicalProfile;
use App\Domains\Profile\Infrastructure\Persistence\Models\PersonalProfile;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TriageStatsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor   = $request->user();
        $isAdmin = $actor instanceof Identity && $actor->hasMembershipRole('PlatformAdmin');

        // Org-scoped identity IDs
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

        // --- Cancer type breakdown ---
        $mpQuery = MedicalProfile::query();
        if ($identityIds !== null) {
            $mpQuery->whereIn('identity_id', $identityIds);
        }
        $total = (clone $mpQuery)->count();

        $cancerTypes = [
            'breast'   => (clone $mpQuery)->where('has_breast_cancer', true)->count(),
            'ovarian'  => (clone $mpQuery)->where('has_ovarian_cancer', true)->count(),
            'cervical' => (clone $mpQuery)->where('has_cervical_cancer', true)->count(),
            'other'    => (clone $mpQuery)->where('has_other_cancer', true)->count(),
        ];
        $cancerTypes['none'] = (clone $mpQuery)
            ->where('has_breast_cancer', false)
            ->where('has_ovarian_cancer', false)
            ->where('has_cervical_cancer', false)
            ->where('has_other_cancer', false)
            ->count();

        // --- Age group breakdown ---
        $ppQuery = PersonalProfile::whereNotNull('birth_date');
        if ($identityIds !== null) {
            $ppQuery->whereIn('identity_id', $identityIds);
        }
        $ageGroups = ['<18' => 0, '18-35' => 0, '36-50' => 0, '51-65' => 0, '>65' => 0];
        foreach ($ppQuery->get(['birth_date']) as $profile) {
            $age = Carbon::parse($profile->birth_date)->age;
            if ($age < 18)      $ageGroups['<18']++;
            elseif ($age <= 35) $ageGroups['18-35']++;
            elseif ($age <= 50) $ageGroups['36-50']++;
            elseif ($age <= 65) $ageGroups['51-65']++;
            else                $ageGroups['>65']++;
        }

        // --- Municipality breakdown ---
        $municipalityQuery = PersonalProfile::whereNotNull('municipality');
        if ($identityIds !== null) {
            $municipalityQuery->whereIn('identity_id', $identityIds);
        }
        $municipalities = $municipalityQuery
            ->select('municipality', DB::raw('count(*) as count'))
            ->groupBy('municipality')
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->map(fn ($r) => ['municipality' => $r->municipality, 'count' => (int) $r->count])
            ->values();

        // --- Risk level distribution ---
        $sessionQuery = EvaluationSession::whereNotNull('risk_level')
            ->where('status', 'completed');
        if ($identityIds !== null) {
            $sessionQuery->whereIn('identity_id', $identityIds);
        }
        $riskLevels = $sessionQuery
            ->select('risk_level', DB::raw('count(*) as count'))
            ->groupBy('risk_level')
            ->get()
            ->pluck('count', 'risk_level')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        // --- Sessions KPIs ---
        $allSessions = EvaluationSession::query();
        if ($identityIds !== null) {
            $allSessions->whereIn('identity_id', $identityIds);
        }
        $sessionStats = [
            'total'       => (clone $allSessions)->count(),
            'completed'   => (clone $allSessions)->where('status', 'completed')->count(),
            'in_progress' => (clone $allSessions)->where('status', 'in_progress')->count(),
            'cancelled'   => (clone $allSessions)->where('status', 'cancelled')->count(),
        ];

        $avgScore = (clone $allSessions)
            ->where('status', 'completed')
            ->whereNotNull('score')
            ->avg('score');

        return response()->json([
            'profiles_total' => $total,
            'cancer_types'   => $cancerTypes,
            'age_groups'     => $ageGroups,
            'municipalities' => $municipalities,
            'risk_levels'    => $riskLevels,
            'sessions'       => $sessionStats,
            'avg_score'      => $avgScore ? round((float) $avgScore, 1) : null,
        ]);
    }
}
