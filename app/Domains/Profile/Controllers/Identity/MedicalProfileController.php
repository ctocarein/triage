<?php

namespace App\Domains\Profile\Controllers\Identity;

use App\Domains\Profile\DTOs\Identity\MedicalProfileStoreRequest;
use App\Domains\Profile\DTOs\Identity\MedicalProfileUpdateRequest;
use App\Domains\Profile\Resources\MedicalProfileResource;
use App\Domains\Profile\Services\MedicalProfileService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicalProfileController extends Controller
{
    public function __construct(private readonly MedicalProfileService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $profile = $this->service->get($request->user());

        if (! $profile) {
            return response()->json(null);
        }

        return (new MedicalProfileResource($profile))->response();
    }

    public function store(MedicalProfileStoreRequest $request): JsonResponse
    {
        $profile = $this->service->create($request->user(), $request->validated());

        return (new MedicalProfileResource($profile))
            ->response()
            ->setStatusCode(201);
    }

    public function update(MedicalProfileUpdateRequest $request): JsonResponse
    {
        $profile = $this->service->update($request->user(), $request->validated());

        return (new MedicalProfileResource($profile))->response();
    }
}
