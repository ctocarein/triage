<?php

namespace App\Domains\Ambassador\Application\Handlers;

use App\Domains\Ambassador\Application\Commands\ActivateAmbassadorCommand;
use App\Domains\Ambassador\Application\Commands\DeactivateAmbassadorCommand;
use App\Domains\Ambassador\Application\Services\AmbassadorActivationService;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Ambassador;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class ActivateAmbassadorHandler
{
    public function __construct(private readonly AmbassadorActivationService $service)
    {
    }

    public function handleActivate(Identity $actor, Identity $target, ?string $organizationId): Ambassador
    {
        $command = new ActivateAmbassadorCommand($actor, $target, $organizationId);

        return $this->service->activate($command->actor, $command->target, $command->organizationId);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handleDeactivate(Identity $actor, array $payload): Ambassador
    {
        $command = new DeactivateAmbassadorCommand($actor, $payload);

        return $this->service->deactivate($command->actor, $command->payload);
    }
}
