<?php

namespace App\Domains\Ambassador\Application\Commands;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class ActivateAmbassadorCommand
{
    public function __construct(
        public readonly Identity $actor,
        public readonly Identity $target,
        public readonly ?string $organizationId,
    )
    {
    }
}
