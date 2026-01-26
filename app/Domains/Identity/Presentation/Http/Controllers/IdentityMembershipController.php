<?php

namespace App\Domains\Identity\Presentation\Http\Controllers;

use App\Domains\Access\Models\Membership;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Identity\Application\Services\IdentityMembershipService;
use App\Domains\Identity\Presentation\Http\Requests\AssignMembershipRequest;
use App\Domains\Identity\Presentation\Http\Requests\MembershipIndexRequest;
use App\Domains\Identity\Presentation\Http\Requests\MembershipUpdateRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class IdentityMembershipController extends Controller
{
    /**
     * @OA\Get(
     *     path="/identity/memberships",
     *     operationId="identityMembershipsIndex",
     *     summary="List memberships",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Paginated list", @OA\JsonContent(ref="#/components/schemas/MembershipCollection")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function index(MembershipIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Membership::class);

        $actor = $request->user();
        $filters = $request->validated();

        $query = Membership::query()->with(['organization:id,name']);

        if (! empty($filters['identity_id'])) {
            $query->where('identity_id', $filters['identity_id']);
        }

        if (! empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if ($actor instanceof Identity && ! $actor->hasMembershipRole('PlatformAdmin')) {
            $organizationIds = $actor->memberships()
                ->where('role', 'OrgManager')
                ->pluck('organization_id')
                ->filter()
                ->all();

            $query->whereIn('organization_id', $organizationIds);
        }

        $perPage = $filters['per_page'] ?? 15;

        return response()->json($query->paginate($perPage));
    }

    /**
     * @OA\Post(
     *     path="/identity/memberships",
     *     operationId="identityAssignMembership",
     *     summary="Assign a membership role to an identity",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/MembershipCreateRequest")
     *     ),
     *     @OA\Response(response=201, description="Membership assigned", @OA\JsonContent(ref="#/components/schemas/MembershipResponse")),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(
        AssignMembershipRequest $request,
        IdentityMembershipService $service
    ): JsonResponse {
        $candidate = new Membership($request->validated());
        $this->authorize('create', $candidate);

        $membership = $service->assign($request->validated(), $request->user());

        return response()->json(['membership' => $membership], 201);
    }

    /**
     * @OA\Get(
     *     path="/identity/memberships/{membership}",
     *     operationId="identityMembershipsShow",
     *     summary="Show a membership",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="membership", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Membership details", @OA\JsonContent(ref="#/components/schemas/MembershipResponse"))
     * )
     */
    public function show(Request $request, Membership $membership): JsonResponse
    {
        $this->authorize('view', $membership);

        return response()->json(['membership' => $membership->load('organization:id,name')]);
    }

    /**
     * @OA\Patch(
     *     path="/identity/memberships/{membership}",
     *     operationId="identityMembershipsUpdate",
     *     summary="Update a membership",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="membership", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/MembershipUpdateRequest")),
     *     @OA\Response(response=200, description="Membership updated", @OA\JsonContent(ref="#/components/schemas/MembershipResponse"))
     * )
     */
    public function update(MembershipUpdateRequest $request, Membership $membership): JsonResponse
    {
        $this->authorize('update', $membership);

        $membership->fill($request->validated())->save();

        return response()->json(['membership' => $membership->load('organization:id,name')]);
    }

    /**
     * @OA\Delete(
     *     path="/identity/memberships/{membership}",
     *     operationId="identityMembershipsDelete",
     *     summary="Deactivate a membership",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="membership", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Membership deactivated", @OA\JsonContent(ref="#/components/schemas/ApiMessage"))
     * )
     */
    public function destroy(Membership $membership): JsonResponse
    {
        $this->authorize('delete', $membership);

        $membership->forceFill(['status' => 'inactive'])->save();

        return response()->json(['message' => 'Membership deactivated.']);
    }
}
