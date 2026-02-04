<?php

namespace App\Console;

use App\Console\Commands\ImportQuestionnaire;
use App\Console\Commands\RecomputeAmbassadorStats;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        ImportQuestionnaire::class,
        RecomputeAmbassadorStats::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('ambassador:recompute-stats')->dailyAt('02:00');
    }
}
