<?php

namespace App\Domains\Profile\Presentation\Http\Controllers;

use App\Domains\Profile\Presentation\Http\Requests\PersonalProfileStoreRequest;
use App\Domains\Profile\Presentation\Http\Requests\PersonalProfileUpdateRequest;
use App\Domains\Profile\Presentation\Http\Resources\PersonalProfileResource;
use App\Domains\Profile\Application\Services\PersonalProfileService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class PersonalProfileController extends Controller
{
    public function __construct(private readonly PersonalProfileService $service)
    {
    }

    /**
     * @OA\Get(
     *     path="/identity/profile/personal",
     *     operationId="identityPersonalProfileIndex",
     *     summary="Get personal profile",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Profile data", @OA\JsonContent(ref="#/components/schemas/PersonalProfileResponse")),
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

        return (new PersonalProfileResource($profile))->response();
    }

    /**
     * @OA\Post(
     *     path="/identity/profile/personal",
     *     operationId="identityPersonalProfileStore",
     *     summary="Create personal profile",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/PersonalProfileCreateRequest")
     *     ),
     *     @OA\Response(response=201, description="Profile created", @OA\JsonContent(ref="#/components/schemas/PersonalProfileResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(PersonalProfileStoreRequest $request): JsonResponse
    {
        $profile = $this->service->create($request->user(), $request->validated());

        return (new PersonalProfileResource($profile))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Put(
     *     path="/identity/profile/personal",
     *     operationId="identityPersonalProfileUpdate",
     *     summary="Update personal profile",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/PersonalProfileUpdateRequest")
     *     ),
     *     @OA\Response(response=200, description="Profile updated", @OA\JsonContent(ref="#/components/schemas/PersonalProfileResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(PersonalProfileUpdateRequest $request): JsonResponse
    {
        $profile = $this->service->update($request->user(), $request->validated());

        return (new PersonalProfileResource($profile))->response();
    }
}
