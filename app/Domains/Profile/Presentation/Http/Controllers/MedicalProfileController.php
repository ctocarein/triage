<?php

namespace App\Domains\Profile\Presentation\Http\Controllers;

use App\Domains\Profile\Presentation\Http\Requests\MedicalProfileStoreRequest;
use App\Domains\Profile\Presentation\Http\Requests\MedicalProfileUpdateRequest;
use App\Domains\Profile\Presentation\Http\Resources\MedicalProfileResource;
use App\Domains\Profile\Application\Services\MedicalProfileService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class MedicalProfileController extends Controller
{
    public function __construct(private readonly MedicalProfileService $service)
    {
    }

    /**
     * @OA\Get(
     *     path="/identity/profile/medical",
     *     operationId="identityMedicalProfileIndex",
     *     summary="Get medical profile",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Profile data", @OA\JsonContent(ref="#/components/schemas/MedicalProfileResponse")),
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

        return (new MedicalProfileResource($profile))->response();
    }

    /**
     * @OA\Post(
     *     path="/identity/profile/medical",
     *     operationId="identityMedicalProfileStore",
     *     summary="Create medical profile",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/MedicalProfileCreateRequest")
     *     ),
     *     @OA\Response(response=201, description="Profile created", @OA\JsonContent(ref="#/components/schemas/MedicalProfileResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(MedicalProfileStoreRequest $request): JsonResponse
    {
        $profile = $this->service->create($request->user(), $request->validated());

        return (new MedicalProfileResource($profile))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Put(
     *     path="/identity/profile/medical",
     *     operationId="identityMedicalProfileUpdate",
     *     summary="Update medical profile",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/MedicalProfileUpdateRequest")
     *     ),
     *     @OA\Response(response=200, description="Profile updated", @OA\JsonContent(ref="#/components/schemas/MedicalProfileResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(MedicalProfileUpdateRequest $request): JsonResponse
    {
        $profile = $this->service->update($request->user(), $request->validated());

        return (new MedicalProfileResource($profile))->response();
    }
}
