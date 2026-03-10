<?php

namespace App\Domains\Identity\Presentation\Http\Controllers;

use App\Domains\Identity\Application\Services\OrgManagerOnboardingService;
use App\Domains\Identity\Infrastructure\Persistence\Models\OrgManagerOnboardingRequest;
use App\Domains\Identity\Presentation\Http\Requests\OrgManagerOnboardRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrgManagerOnboardingController extends Controller
{
    public function __construct(
        private readonly OrgManagerOnboardingService $service,
    ) {}

    /**
     * GET /identity/onboard/requests
     * Liste toutes les demandes d'onboarding (PlatformAdmin).
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $query = OrgManagerOnboardingRequest::with(['organization', 'identity.credentials'])
            ->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        $requests = $query->paginate(20);

        $requests->getCollection()->transform(function (OrgManagerOnboardingRequest $r) {
            $email = $r->identity?->credentials()
                ->where('type', 'email')
                ->value('identifier');

            return [
                'id'               => $r->id,
                'status'           => $r->status,
                'attempt_number'   => $r->attempt_number,
                'reminder_count'   => $r->reminder_count,
                'admin_notified_at'=> $r->admin_notified_at,
                'created_at'       => $r->created_at,
                'email'            => $email,
                'organization'     => $r->organization ? [
                    'id'      => $r->organization->id,
                    'name'    => $r->organization->name,
                    'type'    => $r->organization->type,
                    'country' => $r->organization->country,
                    'city'    => $r->organization->city,
                    'address' => $r->organization->address,
                    'status'  => $r->organization->status,
                ] : null,
            ];
        });

        return response()->json($requests);
    }

    /**
     * POST /identity/onboard/org-manager
     * Crée une demande d'onboarding OrgManager avec vérification email.
     */
    public function register(OrgManagerOnboardRequest $request): JsonResponse
    {
        $result = $this->service->register($request->validated());

        return response()->json($result, 201);
    }

    /**
     * GET /identity/verify-email/{token}
     * Vérifie l'email via le token et passe la demande à pending_validation.
     */
    public function verifyEmail(string $token): JsonResponse
    {
        $result = $this->service->verifyEmail($token);

        return response()->json($result);
    }

    /**
     * POST /identity/onboard/requests/{id}/approve
     * Approuve une demande (PlatformAdmin).
     */
    public function approve(int $id): JsonResponse
    {
        $onboardingRequest = OrgManagerOnboardingRequest::findOrFail($id);
        $this->service->approve($onboardingRequest);

        return response()->json(['message' => 'Demande approuvée avec succès.']);
    }

    /**
     * POST /identity/onboard/requests/{id}/reject
     * Rejette une demande (PlatformAdmin).
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason_code'   => ['required', 'string', 'in:incomplete_info,unknown_org,duplicate,other'],
            'reason_detail' => ['nullable', 'string', 'max:500'],
        ]);

        $onboardingRequest = OrgManagerOnboardingRequest::findOrFail($id);
        $this->service->reject($onboardingRequest, $request->input('reason_code'), $request->input('reason_detail'));

        return response()->json(['message' => 'Demande rejetée.']);
    }
}
