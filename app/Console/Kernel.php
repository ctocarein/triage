<?php

namespace App\Console;

use App\Console\Commands\ImportQuestionnaire;
use App\Console\Commands\CheckEvaluationCatalog;
use App\Console\Commands\CheckRiskSignals;
use App\Console\Commands\CheckRoutingCycles;
use App\Console\Commands\ReplayGoldenPaths;
use App\Console\Commands\RecomputeAmbassadorStats;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        CheckEvaluationCatalog::class,
        CheckRiskSignals::class,
        CheckRoutingCycles::class,
        ImportQuestionnaire::class,
        ReplayGoldenPaths::class,
        RecomputeAmbassadorStats::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('ambassador:recompute-stats')->dailyAt('02:00');
    }
}
