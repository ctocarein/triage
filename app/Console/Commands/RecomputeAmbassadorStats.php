<?php

namespace App\Console\Commands;

use App\Domains\Ambassador\Application\Services\AmbassadorBadgeService;
use App\Domains\Ambassador\Application\Services\AmbassadorLevelService;
use App\Domains\Ambassador\Application\Services\AmbassadorMetricsService;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Ambassador;
use Illuminate\Console\Command;

class RecomputeAmbassadorStats extends Command
{
    protected $signature = 'ambassador:recompute-stats {--ambassador_id=}';

    protected $description = 'Recompute ambassador levels and badges.';

    public function handle(
        AmbassadorMetricsService $metricsService,
        AmbassadorLevelService $levelService,
        AmbassadorBadgeService $badgeService,
    ): int {
        $ambassadorId = $this->option('ambassador_id');

        $query = Ambassador::query()->where('status', 'active');
        if ($ambassadorId) {
            $query->where('id', $ambassadorId);
        }

        $processed = 0;

        $query->orderBy('id')->chunk(100, function ($ambassadors) use (
            $metricsService,
            $levelService,
            $badgeService,
            &$processed
        ) {
            foreach ($ambassadors as $ambassador) {
                $metrics = $metricsService->getMetrics($ambassador);
                $levelService->recomputeLevel($ambassador, $metrics);
                $badgeService->recompute($ambassador);
                $processed++;
            }
        });

        $this->info("Recomputed stats for {$processed} ambassador(s).");

        return self::SUCCESS;
    }
}
