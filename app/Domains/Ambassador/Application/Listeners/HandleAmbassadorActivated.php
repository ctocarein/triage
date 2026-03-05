<?php

namespace App\Domains\Ambassador\Application\Listeners;

use App\Domains\Ambassador\Application\Services\AmbassadorBadgeService;
use App\Domains\Ambassador\Domain\Events\AmbassadorActivated;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Ambassador;

class HandleAmbassadorActivated
{
    public function __construct(private readonly AmbassadorBadgeService $badgeService)
    {
    }

    public function handle(AmbassadorActivated $event): void
    {
        $ambassador = Ambassador::query()->find($event->ambassadorId);
        if (! $ambassador) {
            return;
        }

        $this->badgeService->awardWelcomeBadge($ambassador);
    }
}
