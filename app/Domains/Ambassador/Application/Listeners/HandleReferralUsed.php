<?php

namespace App\Domains\Ambassador\Application\Listeners;

use App\Domains\Ambassador\Application\Services\AmbassadorBadgeService;
use App\Domains\Ambassador\Application\Services\AmbassadorLevelService;
use App\Domains\Ambassador\Application\Services\AmbassadorMetricsService;
use App\Domains\Ambassador\Domain\Events\ReferralUsed;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\AmbassadorReferralUsage;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Referral;

class HandleReferralUsed
{
    public function __construct(
        private readonly AmbassadorMetricsService $metricsService,
        private readonly AmbassadorLevelService $levelService,
        private readonly AmbassadorBadgeService $badgeService,
    ) {
    }

    public function handle(ReferralUsed $event): void
    {
        $referral = Referral::query()->with('ambassador')->find($event->referralId);
        if (! $referral) {
            return;
        }

        if ($event->eventType === 'completed') {
            AmbassadorReferralUsage::query()->create([
                'referral_id' => $referral->id,
                'event_type' => 'completed',
                'source' => 'evaluation',
                'occurred_at' => now(),
            ]);
        }

        $ambassador = $referral->ambassador;
        if (! $ambassador || $ambassador->status !== 'active') {
            return;
        }

        if ($event->eventType === 'completed') {
            $metrics = $this->metricsService->getMetrics($ambassador);
            $this->levelService->recomputeLevel($ambassador, $metrics);
        }

        $this->badgeService->recompute($ambassador);
    }
}
