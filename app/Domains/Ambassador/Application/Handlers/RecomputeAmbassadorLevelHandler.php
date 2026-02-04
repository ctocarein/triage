<?php

namespace App\Domains\Ambassador\Application\Handlers;

use App\Domains\Ambassador\Application\Services\AmbassadorActivationService;
use App\Domains\Ambassador\Application\Services\AmbassadorLevelService;
use App\Domains\Ambassador\Application\Services\AmbassadorMetricsService;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class RecomputeAmbassadorLevelHandler
{
    public function __construct(
        private readonly AmbassadorActivationService $activationService,
        private readonly AmbassadorMetricsService $metricsService,
        private readonly AmbassadorLevelService $levelService,
    ) {
    }

    public function handle(Identity $actor): array
    {
        $ambassador = $this->activationService->requireActiveAmbassador($actor);
        $metrics = $this->metricsService->getMetrics($ambassador);

        return $this->levelService->recomputeLevel($ambassador, $metrics);
    }
}
