<?php

namespace App\Domains\Ambassador\Application\Handlers;

use App\Domains\Ambassador\Application\Commands\RecomputeAmbassadorBadgesCommand;
use App\Domains\Ambassador\Application\Services\AmbassadorActivationService;
use App\Domains\Ambassador\Application\Services\AmbassadorBadgeService;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class RecomputeAmbassadorBadgesHandler
{
    public function __construct(
        private readonly AmbassadorActivationService $activationService,
        private readonly AmbassadorBadgeService $badgeService,
    ) {
    }

    public function handle(Identity $actor): array
    {
        $command = new RecomputeAmbassadorBadgesCommand($actor);
        $ambassador = $this->activationService->requireActiveAmbassador($command->actor);

        return $this->badgeService->recompute($ambassador);
    }
}
