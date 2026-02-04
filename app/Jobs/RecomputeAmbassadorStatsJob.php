<?php

namespace App\Jobs;

use App\Domains\Ambassador\Application\Services\AmbassadorBadgeService;
use App\Domains\Ambassador\Application\Services\AmbassadorLevelService;
use App\Domains\Ambassador\Application\Services\AmbassadorMetricsService;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Ambassador;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class RecomputeAmbassadorStatsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(
        AmbassadorMetricsService $metricsService,
        AmbassadorLevelService $levelService,
        AmbassadorBadgeService $badgeService,
    ): void {
        Ambassador::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->chunk(100, function ($ambassadors) use ($metricsService, $levelService, $badgeService) {
                foreach ($ambassadors as $ambassador) {
                    $metrics = $metricsService->getMetrics($ambassador);
                    $levelService->recomputeLevel($ambassador, $metrics);
                    $badgeService->recompute($ambassador);
                }
            });

        Cache::put('ambassador:recompute:last_run', now()->toDateTimeString(), now()->addDays(7));
        Cache::forget('ambassador:recompute:lock');
    }
}
