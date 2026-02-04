<?php

namespace App\Domains\Profile\Presentation\Http\Controllers;

use App\Domains\Profile\Application\Services\ProfessionalProfileService;
use App\Domains\Profile\Presentation\Http\Requests\ProfessionalProfileStoreRequest;
use App\Domains\Profile\Presentation\Http\Requests\ProfessionalProfileUpdateRequest;
use App\Domains\Profile\Presentation\Http\Resources\ProfessionalProfileResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class ProfessionalProfileController extends Controller
{
    public function __construct(private readonly ProfessionalProfileService $service)
    {
    }

    /**
     * @OA\Get(
     *     path="/identity/profile/pro",
     *     operationId="identityProfessionalProfileIndex",
     *     summary="Get professional profile",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Profile data", @OA\JsonContent(ref="#/components/schemas/ProfessionalProfileResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $profile = $this->service->get($request->user());

        if (! $profile) {
            return response()->json(null);
        }

        return (new ProfessionalProfileResource($profile))->response();
    }

    /**
     * @OA\Post(
     *     path="/identity/profile/pro",
     *     operationId="identityProfessionalProfileStore",
     *     summary="Create professional profile",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/ProfessionalProfileCreateRequest")
     *     ),
     *     @OA\Response(response=201, description="Profile created", @OA\JsonContent(ref="#/components/schemas/ProfessionalProfileResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(ProfessionalProfileStoreRequest $request): JsonResponse
    {
        $profile = $this->service->create($request->user(), $request->validated());

        return (new ProfessionalProfileResource($profile))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Put(
     *     path="/identity/profile/pro",
     *     operationId="identityProfessionalProfileUpdate",
     *     summary="Update professional profile",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/ProfessionalProfileUpdateRequest")
     *     ),
     *     @OA\Response(response=200, description="Profile updated", @OA\JsonContent(ref="#/components/schemas/ProfessionalProfileResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(ProfessionalProfileUpdateRequest $request): JsonResponse
    {
        $profile = $this->service->update($request->user(), $request->validated());

        return (new ProfessionalProfileResource($profile))->response();
    }
}
