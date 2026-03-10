<?php

namespace App\Console\Commands;

use App\Domains\Identity\Application\Services\OrgManagerOnboardingService;
use Illuminate\Console\Command;

class SendOnboardingAdminReminders extends Command
{
    protected $signature = 'onboarding:send-admin-reminders';

    protected $description = 'Envoie des rappels aux admins pour les demandes d\'onboarding OrgManager en attente (J+2, J+5, J+7)';

    public function handle(OrgManagerOnboardingService $service): int
    {
        $sent = $service->sendAdminReminders();

        $this->info("Rappels envoyés : {$sent}");

        return Command::SUCCESS;
    }
}
