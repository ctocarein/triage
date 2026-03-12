<?php

namespace App\Domains\Identity\Presentation\Http\Controllers;

use App\Domains\Identity\Application\Services\IdentityAuthService;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Identity\Presentation\Http\Requests\IdentityIndexRequest;
use App\Domains\Identity\Presentation\Http\Requests\IdentityUpdateRequest;
use App\Domains\Identity\Presentation\Http\Requests\RegisterIdentityRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class IdentityController extends Controller
{
    /**
     * @OA\Get(
     *     path="/identity",
     *     operationId="identityIndex",
     *     summary="List identities",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="kind", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="role", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="organization_id", in="query", @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Paginated list", @OA\JsonContent(ref="#/components/schemas/IdentityCollection")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function index(IdentityIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Identity::class);

        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Forbidden.');
        }

        $filters = $request->validated();
        $query = Identity::query()->with(['memberships.organization:id,name', 'credentials']);

        if (! empty($filters['kind'])) {
            $query->where('kind', $filters['kind']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['organization_id']) || ! empty($filters['role'])) {
            $query->whereHas('memberships', function ($membershipQuery) use ($filters) {
                if (! empty($filters['organization_id'])) {
                    $membershipQuery->where('organization_id', $filters['organization_id']);
                }

                if (! empty($filters['role'])) {
                    $membershipQuery->where('role', $filters['role']);
                }
            });
        }

        if (! $actor->hasMembershipRole('PlatformAdmin') && $actor->hasMembershipRole('OrgManager')) {
            $organizationIds = $actor->memberships()
                ->where('role', 'OrgManager')
                ->pluck('organization_id')
                ->filter()
                ->all();

            $query->whereHas('memberships', function ($membershipQuery) use ($organizationIds) {
                $membershipQuery->whereIn('organization_id', $organizationIds);
            });
        }

        if (! empty($filters['phone'])) {
            $query->whereHas('credentials', function ($q) use ($filters) {
                $q->where('type', 'phone')
                  ->where('identifier', 'like', '%' . $filters['phone'] . '%');
            });
        }

        $perPage = $filters['per_page'] ?? 15;

        $paginated = $query->paginate($perPage);

        // Append _email and _phone to each identity for frontend consumption
        $paginated->getCollection()->transform(function (Identity $identity) {
            $identity->setAttribute('_email', $identity->credentials->where('type', 'email')->first()?->identifier);
            $identity->setAttribute('_phone', $identity->credentials->where('type', 'phone')->first()?->identifier);
            return $identity;
        });

        return response()->json($paginated);
    }

    /**
     * @OA\Post(
     *     path="/identity",
     *     operationId="identityStore",
     *     summary="Create a new identity",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/IdentityRegisterRequest")),
     *     @OA\Response(response=201, description="Identity created", @OA\JsonContent(ref="#/components/schemas/IdentityRegisterResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(RegisterIdentityRequest $request, IdentityAuthService $service): JsonResponse
    {
        $this->authorize('create', Identity::class);

        $payload = $service->register($request->validated());

        return response()->json($payload, 201);
    }

    /**
     * @OA\Get(
     *     path="/identity/me",
     *     operationId="identityMe",
     *     summary="Get the current identity",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Identity context", @OA\JsonContent(ref="#/components/schemas/IdentityResponse")),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function me(Request $request, IdentityAuthService $service): JsonResponse
    {
        $identity = $request->user()?->load([
            'memberships.organization:id,name',
            'credentials',
            'personalProfile',
        ]);

        $securityGate = $service->getSecurityStatus($identity, null);

        return response()->json([
            'identity'      => $identity,
            'security_gate' => $securityGate['security_gate'],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/identity/{identity}",
     *     operationId="identityShow",
     *     summary="Show an identity",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="identity", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Identity details", @OA\JsonContent(ref="#/components/schemas/IdentityResponse")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function show(Identity $identity): JsonResponse
    {
        $this->authorize('view', $identity);

        $identity->load(['memberships.organization:id,name', 'credentials']);

        return response()->json(['identity' => $identity]);
    }

    /**
     * @OA\Patch(
     *     path="/identity/{identity}",
     *     operationId="identityUpdate",
     *     summary="Update an identity",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="identity", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/IdentityUpdateRequest")),
     *     @OA\Response(response=200, description="Identity updated", @OA\JsonContent(ref="#/components/schemas/IdentityResponse"))
     * )
     */
    public function update(IdentityUpdateRequest $request, Identity $identity): JsonResponse
    {
        $this->authorize('update', $identity);

        $identity->fill($request->validated())->save();

        return response()->json(['identity' => $identity]);
    }

    /**
     * @OA\Delete(
     *     path="/identity/{identity}",
     *     operationId="identityDelete",
     *     summary="Deactivate an identity",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="identity", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Identity deactivated", @OA\JsonContent(ref="#/components/schemas/ApiMessage"))
     * )
     */
    public function destroy(Identity $identity): JsonResponse
    {
        $this->authorize('delete', $identity);

        $identity->forceFill(['status' => 'suspended'])->save();

        return response()->json(['message' => 'Identity suspended.']);
    }
}
