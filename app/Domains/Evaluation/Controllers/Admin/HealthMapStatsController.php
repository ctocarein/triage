<?php

namespace App\Domains\Evaluation\Controllers\Admin;

use App\Domains\Access\Models\Membership;
use App\Domains\Evaluation\Domain\Policies\IdentityEvaluationPolicy;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Organization\Models\HealthDistrict;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What the national map draws: every health district, whether it is covered,
 * and what has happened there.
 *
 * The whole country is returned, districts with no activity included. Where the
 * platform is absent is as informative as where it is present, and a map that
 * only shows the covered districts silently reads as full coverage.
 *
 * Figures are withheld below a disclosure floor. In a district where three
 * women were evaluated, "3 women, 1 urgent referral" identifies someone to
 * anyone who knows the area; on cancer data that is not a theoretical concern.
 * The district still appears and still reads as covered -- hiding it would
 * suggest an absence of coverage and push resources where they already are.
 */
class HealthMapStatsController extends Controller
{
    /**
     * GET /admin/health-map/stats
     */
    public function __invoke(): JsonResponse
    {
        $floor = (int) config('health-map.min_disclosure_count', 5);

        $activity  = $this->activityByDistrict();
        $urgent    = $this->urgentReferralsByDistrict();
        $capacity  = $this->evaluatorCapacityByDistrict();

        $districts = HealthDistrict::query()
            ->with('region')
            ->orderBy('name')
            ->get()
            ->map(function (HealthDistrict $district) use ($activity, $urgent, $capacity, $floor): array {
                $stats   = $activity->get($district->id);
                $reached = (int) ($stats->women_reached ?? 0);

                // Couvert des qu'une femme y a ete evaluee ou qu'un evaluateur
                // y exerce : la capacite compte meme sans activite encore.
                $evaluators = (int) ($capacity[$district->id] ?? 0);
                $covered    = $reached > 0 || $evaluators > 0;

                $disclosed = $reached >= $floor;

                return [
                    'code'      => $district->code,
                    'slug'      => $district->slug,
                    'name'      => $district->name,
                    'latitude'  => $district->latitude,
                    'longitude' => $district->longitude,

                    'region' => [
                        'code' => $district->region?->code,
                        'name' => $district->region?->name,
                        'pres' => $district->region?->pres,
                    ],

                    // La carte sanitaire ne fournit pas d'affectation district
                    // -> region officielle : agreger par region reste indicatif
                    // tant que la DIIS ne l'a pas validee, et le client doit
                    // pouvoir le dire a celui qui regarde.
                    'region_assignment_verified' => $district->region_assignment_verified,

                    'covered'    => $covered,
                    'evaluators' => $evaluators,

                    // Retenus sous le seuil : le district reste sur la carte,
                    // ses chiffres non.
                    'disclosed'               => $disclosed,
                    'women_reached'           => $disclosed ? $reached : null,
                    'evaluations_completed'   => $disclosed ? (int) ($stats->evaluations_completed ?? 0) : null,
                    'evaluations_in_progress' => $disclosed ? (int) ($stats->evaluations_in_progress ?? 0) : null,
                    'high_risk'               => $disclosed ? (int) ($stats->high_risk ?? 0) : null,
                    'urgent_referrals'        => $disclosed ? (int) ($urgent[$district->id] ?? 0) : null,
                ];
            });

        return response()->json([
            'min_disclosure_count' => $floor,

            // De quoi afficher un avertissement honnete plutot que de laisser
            // croire a une carte complete.
            'coverage' => [
                'districts_total'             => $districts->count(),
                'districts_covered'           => $districts->where('covered', true)->count(),
                'districts_with_coordinates'  => $districts->whereNotNull('latitude')->count(),
            ],

            'health_districts' => $districts->values(),
        ]);
    }

    /**
     * Activite par district, rattachee au district de la beneficiaire et non a
     * celui de l'evaluatrice : la carte dit ou vivent les femmes touchees.
     *
     * @return Collection<int, object>
     */
    private function activityByDistrict(): Collection
    {
        return EvaluationSession::query()
            ->join('personal_profiles', 'personal_profiles.identity_id', '=', 'evaluation_sessions.identity_id')
            ->whereNull('personal_profiles.deleted_at')
            ->whereNotNull('personal_profiles.health_district_id')
            ->groupBy('personal_profiles.health_district_id')
            ->select([
                'personal_profiles.health_district_id as district_id',
                DB::raw('COUNT(DISTINCT evaluation_sessions.identity_id) as women_reached'),
                DB::raw("SUM(CASE WHEN evaluation_sessions.status = 'completed' THEN 1 ELSE 0 END) as evaluations_completed"),
                DB::raw("SUM(CASE WHEN evaluation_sessions.status = 'in_progress' THEN 1 ELSE 0 END) as evaluations_in_progress"),
                DB::raw("SUM(CASE WHEN evaluation_sessions.risk_level IN ('high','very_high') THEN 1 ELSE 0 END) as high_risk"),
            ])
            ->get()
            ->keyBy('district_id');
    }

    /**
     * Les orientations urgentes vivent dans le JSON de contexte : une requete
     * separee evite d'ecrire une extraction JSON dans un agregat, dont la
     * syntaxe differe entre MySQL et SQLite.
     *
     * @return Collection<int, int>
     */
    private function urgentReferralsByDistrict(): Collection
    {
        return EvaluationSession::query()
            ->join('personal_profiles', 'personal_profiles.identity_id', '=', 'evaluation_sessions.identity_id')
            ->whereNull('personal_profiles.deleted_at')
            ->whereNotNull('personal_profiles.health_district_id')
            ->whereNotNull('evaluation_sessions.context->urgent_referral')
            ->groupBy('personal_profiles.health_district_id')
            ->select([
                'personal_profiles.health_district_id as district_id',
                DB::raw('COUNT(*) as total'),
            ])
            ->pluck('total', 'district_id');
    }

    /**
     * Combien d'evaluateurs exercent dans le district.
     *
     * Se deduit des structures rattachees au district, pas des sessions
     * conduites : c'est une mesure de capacite. Un district ou une ambassadrice
     * exerce sans avoir encore evalue personne est couvert, et le voir vide
     * ferait envoyer des moyens la ou il y en a deja.
     *
     * @return Collection<int, int>
     */
    private function evaluatorCapacityByDistrict(): Collection
    {
        return Membership::query()
            ->join('organizations', 'organizations.id', '=', 'memberships.organization_id')
            ->whereNotNull('organizations.health_district_id')
            ->where('memberships.status', 'active')
            ->whereIn('memberships.role', IdentityEvaluationPolicy::EVALUATOR_ROLES)
            ->groupBy('organizations.health_district_id')
            ->select([
                'organizations.health_district_id as district_id',
                DB::raw('COUNT(DISTINCT memberships.identity_id) as total'),
            ])
            ->pluck('total', 'district_id');
    }
}
