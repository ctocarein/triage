<?php

namespace App\Domains\Ambassador\Application\Queries;

use App\Domains\Ambassador\Application\Services\AmbassadorActivationService;
use App\Domains\Ambassador\Application\Services\AmbassadorBadgeService;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use Illuminate\Support\Collection;

class GetAmbassadorBadgesQuery
{
    public function __construct(
        private readonly AmbassadorActivationService $activationService,
        private readonly AmbassadorBadgeService $badgeService,
    ) {
    }

    public function handle(Identity $actor): Collection
    {
        $ambassador = $this->activationService->requireActiveAmbassador($actor);

        return $this->badgeService->list($ambassador);
    }
}
