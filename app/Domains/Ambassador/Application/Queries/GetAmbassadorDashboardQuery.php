<?php

namespace App\Domains\Ambassador\Application\Queries;

use App\Domains\Ambassador\Application\Services\AmbassadorActivationService;
use App\Domains\Ambassador\Application\Services\AmbassadorBadgeService;
use App\Domains\Ambassador\Application\Services\AmbassadorLevelService;
use App\Domains\Ambassador\Application\Services\AmbassadorMetricsService;
use App\Domains\Ambassador\Presentation\Http\Resources\AmbassadorResource;
use App\Domains\Ambassador\Presentation\Http\Resources\BadgeResource;
use App\Domains\Ambassador\Presentation\Http\Resources\AmbassadorMetricsResource;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class GetAmbassadorDashboardQuery
{
    public function __construct(
        private readonly AmbassadorActivationService $activationService,
        private readonly AmbassadorMetricsService $metricsService,
        private readonly AmbassadorBadgeService $badgeService,
        private readonly AmbassadorLevelService $levelService,
    ) {
    }

    public function handle(Identity $actor): array
    {
        $ambassador = $this->activationService->requireActiveAmbassador($actor);
        $metrics = $this->metricsService->getMetrics($ambassador);
        $badges = $this->badgeService->list($ambassador);
        $level = $this->levelService->getCurrentLevel($ambassador);

        return [
            'ambassador' => (new AmbassadorResource($ambassador))->toArray(request()),
            'metrics' => (new AmbassadorMetricsResource($metrics))->toArray(request()),
            'badges' => BadgeResource::collection($badges)->resolve(),
            'level' => $level,
        ];
    }
}
