<?php

namespace App\Domains\Profile\Controllers\Identity;

use App\Domains\Profile\DTOs\Identity\PersonalProfileStoreRequest;
use App\Domains\Profile\DTOs\Identity\PersonalProfileUpdateRequest;
use App\Domains\Profile\Resources\PersonalProfileResource;
use App\Domains\Profile\Services\PersonalProfileService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonalProfileController extends Controller
{
    public function __construct(private readonly PersonalProfileService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $profile = $this->service->get($request->user());

        if (! $profile) {
            return response()->json(null);
        }

        return (new PersonalProfileResource($profile))->response();
    }

    public function store(PersonalProfileStoreRequest $request): JsonResponse
    {
        $profile = $this->service->create($request->user(), $request->validated());

        return (new PersonalProfileResource($profile))
            ->response()
            ->setStatusCode(201);
    }

    public function update(PersonalProfileUpdateRequest $request): JsonResponse
    {
        $profile = $this->service->update($request->user(), $request->validated());

        return (new PersonalProfileResource($profile))->response();
    }
}
