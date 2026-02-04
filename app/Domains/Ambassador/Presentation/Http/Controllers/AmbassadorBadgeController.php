<?php

namespace App\Domains\Ambassador\Presentation\Http\Controllers;

use App\Domains\Ambassador\Application\Queries\GetAmbassadorBadgesQuery;
use App\Domains\Ambassador\Presentation\Http\Resources\BadgeResource;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class AmbassadorBadgeController extends Controller
{
    public function __construct(private readonly GetAmbassadorBadgesQuery $badgesQuery) {}

    /**
     * @OA\Get(
     *     path="/ambassador/badges",
     *     operationId="ambassadorBadgesIndex",
     *     summary="List ambassador badges",
     *     tags={"Ambassador"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Badge list", @OA\JsonContent(ref="#/components/schemas/AmbassadorBadgeCollection")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Only identities can access badges.');
        }

        $badges = $this->badgesQuery->handle($actor);

        return BadgeResource::collection($badges)->response();
    }
}
