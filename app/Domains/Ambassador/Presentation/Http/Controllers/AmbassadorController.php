<?php

namespace App\Domains\Ambassador\Presentation\Http\Controllers;

use App\Domains\Ambassador\Application\Handlers\ActivateAmbassadorHandler;
use App\Domains\Ambassador\Application\Handlers\RecomputeAmbassadorBadgesHandler;
use App\Domains\Ambassador\Application\Handlers\RecomputeAmbassadorLevelHandler;
use App\Domains\Ambassador\Application\Queries\GetAmbassadorDashboardQuery;
use App\Domains\Ambassador\Application\Queries\GetAmbassadorMetricsQuery;
use App\Domains\Ambassador\Application\Queries\GetAmbassadorProfileQuery;
use App\Domains\Ambassador\Presentation\Http\Requests\ActivateAmbassadorRequest;
use App\Domains\Ambassador\Presentation\Http\Requests\DeactivateAmbassadorRequest;
use App\Domains\Ambassador\Presentation\Http\Resources\AmbassadorMetricsResource;
use App\Domains\Ambassador\Presentation\Http\Resources\AmbassadorResource;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class AmbassadorController extends Controller
{
    public function __construct(
        private readonly ActivateAmbassadorHandler $activateHandler,
        private readonly GetAmbassadorProfileQuery $profileQuery,
        private readonly GetAmbassadorDashboardQuery $dashboardQuery,
        private readonly GetAmbassadorMetricsQuery $metricsQuery,
        private readonly RecomputeAmbassadorBadgesHandler $badgesHandler,
        private readonly RecomputeAmbassadorLevelHandler $levelHandler,
    ) {
    }

    /**
     * @OA\Get(
     *     path="/ambassador/profile",
     *     operationId="ambassadorProfile",
     *     summary="Get ambassador profile",
     *     tags={"Ambassador"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Ambassador profile", @OA\JsonContent(ref="#/components/schemas/AmbassadorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/ApiMessage"))
     * )
     */
    public function profile(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Only identities can access ambassador profiles.');
        }

        $ambassador = $this->profileQuery->handle($actor);

        return (new AmbassadorResource($ambassador))->response();
    }

    /**
     * @OA\Post(
     *     path="/ambassador/activate",
     *     operationId="ambassadorActivate",
     *     summary="Activate ambassador profile",
     *     description="PlatformAdmin only. Activates a beneficiary as ambassador. Defaults organization_id to the PlatformAdmin organization when omitted.",
     *     tags={"Ambassador"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ActivateAmbassadorRequest")),
     *     @OA\Response(response=200, description="Ambassador activated", @OA\JsonContent(ref="#/components/schemas/AmbassadorResponseWithMessage")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/ApiMessage"))
     * )
     */
    public function activate(ActivateAmbassadorRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Only identities can activate ambassadors.');
        }

        $payload = $request->validated();
        $target = Identity::query()->find($payload['identity_id']);
        if ($target === null) {
            abort(404, 'Identity not found.');
        }

        $ambassador = $this->activateHandler->handleActivate(
            $actor,
            $target,
            $payload['organization_id'] ?? null
        );

        return (new AmbassadorResource($ambassador))
            ->additional(['message' => 'Ambassador activated.'])
            ->response();
    }

    /**
     * @OA\Post(
     *     path="/ambassador/deactivate",
     *     operationId="ambassadorDeactivate",
     *     summary="Deactivate ambassador profile",
     *     tags={"Ambassador"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/DeactivateAmbassadorRequest")),
     *     @OA\Response(response=200, description="Ambassador deactivated", @OA\JsonContent(ref="#/components/schemas/AmbassadorResponseWithMessage")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function deactivate(DeactivateAmbassadorRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Only identities can deactivate ambassadors.');
        }

        $ambassador = $this->activateHandler->handleDeactivate($actor, $request->validated());

        return (new AmbassadorResource($ambassador))
            ->additional(['message' => 'Ambassador deactivated.'])
            ->response();
    }

    /**
     * @OA\Get(
     *     path="/ambassador/metrics",
     *     operationId="ambassadorMetrics",
     *     summary="Get ambassador metrics",
     *     tags={"Ambassador"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Ambassador metrics", @OA\JsonContent(ref="#/components/schemas/AmbassadorMetricsResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function metrics(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Only identities can access ambassador metrics.');
        }

        $metrics = $this->metricsQuery->handle($actor);

        return (new AmbassadorMetricsResource($metrics))->response();
    }

    /**
     * @OA\Get(
     *     path="/ambassador/dashboard",
     *     operationId="ambassadorDashboard",
     *     summary="Get ambassador dashboard",
     *     tags={"Ambassador"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Ambassador dashboard", @OA\JsonContent(ref="#/components/schemas/AmbassadorDashboardResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function dashboard(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Only identities can access ambassador dashboard.');
        }

        $dashboard = $this->dashboardQuery->handle($actor);

        return response()->json($dashboard);
    }

    /**
     * @OA\Post(
     *     path="/ambassador/badges/recompute",
     *     operationId="ambassadorRecomputeBadges",
     *     summary="Recompute ambassador badges",
     *     tags={"Ambassador"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Badges recomputed", @OA\JsonContent(ref="#/components/schemas/AmbassadorRecomputeBadgesResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function recomputeBadges(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Only identities can recompute badges.');
        }

        $badges = $this->badgesHandler->handle($actor);

        return response()->json([
            'message' => 'Badges recomputed.',
            'badges' => $badges,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/ambassador/level/recompute",
     *     operationId="ambassadorRecomputeLevel",
     *     summary="Recompute ambassador level",
     *     tags={"Ambassador"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Level recomputed", @OA\JsonContent(ref="#/components/schemas/AmbassadorRecomputeLevelResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function recomputeLevel(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Only identities can recompute levels.');
        }

        $level = $this->levelHandler->handle($actor);

        return response()->json([
            'message' => 'Level recomputed.',
            'level' => $level,
        ]);
    }

}
