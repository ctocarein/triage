<?php

namespace App\Domains\Ambassador\Application\Commands;

use App\Domains\Ambassador\Infrastructure\Persistence\Models\Referral;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class RevokeReferralCommand
{
    public function __construct(
        public readonly Identity $actor,
        public readonly Referral $referral,
    ) {
    }
}
