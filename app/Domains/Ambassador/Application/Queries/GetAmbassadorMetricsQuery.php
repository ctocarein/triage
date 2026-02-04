<?php

namespace App\Domains\Ambassador\Application\Queries;

use App\Domains\Ambassador\Application\Services\AmbassadorActivationService;
use App\Domains\Ambassador\Application\Services\AmbassadorMetricsService;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class GetAmbassadorMetricsQuery
{
    public function __construct(
        private readonly AmbassadorActivationService $activationService,
        private readonly AmbassadorMetricsService $metricsService,
    ) {
    }

    public function handle(Identity $actor): array
    {
        $ambassador = $this->activationService->requireActiveAmbassador($actor);

        return $this->metricsService->getMetrics($ambassador);
    }
}
