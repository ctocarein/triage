<?php

namespace App\Domains\Organization\Presentation\Http\Controllers;

use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Presentation\Http\Requests\OrganizationIndexRequest;
use App\Domains\Organization\Presentation\Http\Requests\OrganizationStoreRequest;
use App\Domains\Organization\Presentation\Http\Requests\OrganizationUpdateRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class OrganizationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/organizations",
     *     operationId="organizationsIndex",
     *     summary="List organizations",
     *     tags={"Organizations"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Paginated list", @OA\JsonContent(ref="#/components/schemas/OrganizationCollection")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function index(OrganizationIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Organization::class);

        $actor = $request->user();
        $filters = $request->validated();
        $query = Organization::query();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        if ($actor && method_exists($actor, 'hasMembershipRole')
            && ! $actor->hasMembershipRole('PlatformAdmin')
            && $actor->hasMembershipRole('OrgManager')
        ) {
            $organizationIds = $actor->memberships()
                ->where('role', 'OrgManager')
                ->pluck('organization_id')
                ->filter()
                ->all();

            $query->whereIn('id', $organizationIds);
        }

        $perPage = $filters['per_page'] ?? 15;

        return response()->json($query->paginate($perPage));
    }

    /**
     * @OA\Post(
     *     path="/organizations",
     *     operationId="organizationsStore",
     *     summary="Create an organization",
     *     tags={"Organizations"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/OrganizationCreateRequest")),
     *     @OA\Response(response=201, description="Organization created", @OA\JsonContent(ref="#/components/schemas/OrganizationResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(OrganizationStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Organization::class);

        $organization = Organization::create([
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'status' => $request->input('status', 'active'),
        ]);

        return response()->json(['organization' => $organization], 201);
    }

    /**
     * @OA\Get(
     *     path="/organizations/{organization}",
     *     operationId="organizationsShow",
     *     summary="Show an organization",
     *     tags={"Organizations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="organization", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Organization details", @OA\JsonContent(ref="#/components/schemas/OrganizationResponse")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function show(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        return response()->json(['organization' => $organization]);
    }

    /**
     * @OA\Patch(
     *     path="/organizations/{organization}",
     *     operationId="organizationsUpdate",
     *     summary="Update an organization",
     *     tags={"Organizations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="organization", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/OrganizationUpdateRequest")),
     *     @OA\Response(response=200, description="Organization updated", @OA\JsonContent(ref="#/components/schemas/OrganizationResponse"))
     * )
     */
    public function update(OrganizationUpdateRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);

        $organization->fill($request->validated())->save();

        return response()->json(['organization' => $organization]);
    }

    /**
     * @OA\Delete(
     *     path="/organizations/{organization}",
     *     operationId="organizationsDelete",
     *     summary="Deactivate an organization",
     *     tags={"Organizations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="organization", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Organization deactivated", @OA\JsonContent(ref="#/components/schemas/ApiMessage"))
     * )
     */
    public function destroy(Organization $organization): JsonResponse
    {
        $this->authorize('delete', $organization);

        $organization->forceFill(['status' => 'inactive'])->save();

        return response()->json(['message' => 'Organization deactivated.']);
    }
}
