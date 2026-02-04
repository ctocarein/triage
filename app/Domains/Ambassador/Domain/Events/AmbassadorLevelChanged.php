<?php

namespace App\Domains\Ambassador\Domain\Events;

class AmbassadorLevelChanged
{
    public function __construct(
        public readonly string $ambassadorId,
        public readonly string $levelCode,
    ) {
    }
}
