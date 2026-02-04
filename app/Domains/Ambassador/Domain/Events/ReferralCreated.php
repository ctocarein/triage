<?php

namespace App\Domains\Ambassador\Domain\Events;

class ReferralCreated
{
    public function __construct(
        public readonly string $referralId,
        public readonly string $ambassadorId,
    ) {
    }
}
