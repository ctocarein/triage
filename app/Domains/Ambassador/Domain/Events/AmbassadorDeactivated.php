<?php

namespace App\Domains\Ambassador\Domain\Events;

class AmbassadorDeactivated
{
    public function __construct(
        public readonly string $ambassadorId,
        public readonly string $userId,
        public readonly ?string $reason = null,
    ) {
    }
}
