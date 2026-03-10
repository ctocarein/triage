<?php

namespace App\Domains\Identity\Presentation\Http\Controllers\Admin;

use App\Domains\Identity\Application\Services\OrgManagerOnboardingService;
use App\Domains\Identity\Infrastructure\Persistence\Models\OrgManagerOnboardingRequest;
use App\Domains\Identity\Presentation\Http\Requests\OrgManagerOnboardingIndexRequest;
use App\Domains\Identity\Presentation\Http\Requests\OrgManagerRejectRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class OrgManagerOnboardingAdminController extends Controller
{
    public function __construct(
        private readonly OrgManagerOnboardingService $service,
    ) {}

    /**
     * GET /admin/onboarding
     *
     * Liste les demandes d'onboarding avec filtres optionnels.
     */
    public function index(OrgManagerOnboardingIndexRequest $request): JsonResponse
    {
        $query = OrgManagerOnboardingRequest::query()
            ->with(['identity.credentials', 'organization']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->input('organization_id'));
        }

        $perPage = (int) ($request->input('per_page', 20));
        $results = $query->latest()->paginate($perPage);

        return response()->json($results);
    }

    /**
     * PATCH /admin/onboarding/{id}/approve
     *
     * Approuve une demande d'onboarding.
     */
    public function approve(OrgManagerOnboardingRequest $onboardingRequest): JsonResponse
    {
        $this->service->approve($onboardingRequest);

        return response()->json(['message' => 'Demande approuvée avec succès.']);
    }

    /**
     * PATCH /admin/onboarding/{id}/reject
     *
     * Rejette une demande d'onboarding avec un motif.
     */
    public function reject(OrgManagerRejectRequest $request, OrgManagerOnboardingRequest $onboardingRequest): JsonResponse
    {
        $validated = $request->validated();

        $this->service->reject(
            $onboardingRequest,
            $validated['reason_code'],
            $validated['reason_detail'] ?? null,
        );

        return response()->json(['message' => 'Demande rejetée.']);
    }
}
