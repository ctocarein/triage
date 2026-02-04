<?php

namespace App\Domains\Ambassador\Domain\Events;

class AmbassadorActivated
{
    public function __construct(
        public readonly string $ambassadorId,
        public readonly string $userId,
    ) {
    }
}
