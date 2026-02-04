<?php

namespace App\Domains\Ambassador\Application\Commands;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class RecomputeAmbassadorBadgesCommand
{
    public function __construct(public readonly Identity $actor)
    {
    }
}
