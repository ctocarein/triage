<?php

namespace App\Domains\Ambassador\Domain\Events;

class BadgeEarned
{
    public function __construct(
        public readonly string $ambassadorId,
        public readonly string $badgeCode,
    ) {
    }
}
