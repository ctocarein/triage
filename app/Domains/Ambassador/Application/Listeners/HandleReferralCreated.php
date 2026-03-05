<?php

namespace App\Domains\Ambassador\Application\Listeners;

use App\Domains\Ambassador\Application\Services\AmbassadorBadgeService;
use App\Domains\Ambassador\Domain\Events\ReferralCreated;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Ambassador;

class HandleReferralCreated
{
    public function __construct(private readonly AmbassadorBadgeService $badgeService)
    {
    }

    public function handle(ReferralCreated $event): void
    {
        $ambassador = Ambassador::query()->find($event->ambassadorId);
        if (! $ambassador || $ambassador->status !== 'active') {
            return;
        }

        $this->badgeService->awardFirstLinkBadge($ambassador);
    }
}
