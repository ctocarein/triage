<?php

namespace App\Domains\Identity\Presentation\Http\Controllers\Admin;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();

        // Determine org scope: PlatformAdmin sees all, OrgManager sees their org
        $isAdmin = $actor instanceof Identity && $actor->hasMembershipRole('PlatformAdmin');
        $orgIds  = [];
        if (! $isAdmin && $actor instanceof Identity) {
            $orgIds = $actor->memberships()
                ->where('role', 'OrgManager')
                ->pluck('organization_id')
                ->filter()
                ->values()
                ->all();
        }

        $patientRoles = ['Beneficiary', 'Ambassador', 'AgentField'];

        $query = Identity::query()
            ->with([
                'personalProfile',
                'medicalProfile',
                'credentials',
                'evaluationSessions' => fn ($q) => $q->latest('started_at')->limit(1),
                'memberships.organization:id,name',
            ])
            ->where('kind', 'person')
            ->whereHas('memberships', function ($q) use ($orgIds, $patientRoles) {
                $q->whereIn('role', $patientRoles);
                if (! empty($orgIds)) {
                    $q->whereIn('organization_id', $orgIds);
                }
            });

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('personalProfile', fn ($pp) =>
                    $pp->where('first_name', 'like', "%{$search}%")
                       ->orWhere('last_name', 'like', "%{$search}%")
                       ->orWhere('commune', 'like', "%{$search}%")
                )->orWhereHas('credentials', fn ($c) =>
                    $c->where('identifier', 'like', "%{$search}%")
                );
            });
        }

        if ($request->filled('commune')) {
            $query->whereHas('personalProfile', fn ($q) =>
                $q->where('commune', $request->input('commune'))
            );
        }

        if ($request->filled('organization_id') && $isAdmin) {
            $query->whereHas('memberships', fn ($q) =>
                $q->where('organization_id', $request->input('organization_id'))
                  ->where('role', 'Beneficiary')
            );
        }

        if ($request->filled('role') && in_array($request->input('role'), $patientRoles)) {
            $query->whereHas('memberships', fn ($q) =>
                $q->where('role', $request->input('role'))
            );
        }

        if ($request->filled('risk_level')) {
            $query->whereHas('evaluationSessions', fn ($q) =>
                $q->where('risk_level', $request->input('risk_level'))
                  ->where('status', 'completed')
            );
        }

        $paginated = $query
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        $paginated->getCollection()->transform(function (Identity $identity) use ($orgIds, $patientRoles) {
            $lastSession = $identity->evaluationSessions->first();
            $activeMemberships = $identity->memberships
                ->whereIn('role', $patientRoles);
            $primaryMembership = $activeMemberships->first();
            $org = $primaryMembership?->organization;
            $roles = $activeMemberships->pluck('role')->unique()->values()->all();

            return [
                'id'           => $identity->id,
                'status'       => $identity->status,
                'created_at'   => $identity->created_at,
                '_email'       => $identity->credentials->where('type', 'email')->first()?->identifier,
                '_phone'       => $identity->credentials->where('type', 'phone')->first()?->identifier,
                'roles'        => $roles,
                'organization' => $org ? ['id' => $org->id, 'name' => $org->name] : null,
                'personal'     => $identity->personalProfile ? [
                    'first_name'      => $identity->personalProfile->first_name,
                    'last_name'       => $identity->personalProfile->last_name,
                    'gender'          => $identity->personalProfile->gender,
                    'age'             => $identity->personalProfile->age,
                    'commune'         => $identity->personalProfile->commune,
                    'birth_date'      => $identity->personalProfile->birth_date,
                    'education_level' => $identity->personalProfile->education_level,
                    'occupation'      => $identity->personalProfile->occupation,
                    'cmu'             => $identity->personalProfile->cmu,
                ] : null,
                'medical'      => $identity->medicalProfile ? [
                    'has_breast_cancer'     => $identity->medicalProfile->has_breast_cancer,
                    'has_ovarian_cancer'    => $identity->medicalProfile->has_ovarian_cancer,
                    'has_cervical_cancer'   => $identity->medicalProfile->has_cervical_cancer,
                    'has_other_cancer'      => $identity->medicalProfile->has_other_cancer,
                    'blood_type'            => $identity->medicalProfile->blood_type,
                    'is_pregnant'           => $identity->medicalProfile->is_pregnant,
                    'family_cancer_history' => $identity->medicalProfile->family_cancer_history,
                    'last_screening_date'   => $identity->medicalProfile->last_screening_date,
                    'menopause_status'      => $identity->medicalProfile->menopause_status,
                    'exposed_to_smoke'      => $identity->medicalProfile->exposed_to_smoke,
                    'weight'                => $identity->medicalProfile->weight,
                    'height'                => $identity->medicalProfile->height,
                ] : null,
                'last_session' => $lastSession ? [
                    'uuid'       => $lastSession->public_id,
                    'status'     => $lastSession->status,
                    'score'      => $lastSession->score,
                    'risk_level' => $lastSession->risk_level,
                    'started_at' => $lastSession->started_at,
                ] : null,
            ];
        });

        return response()->json($paginated);
    }
}
