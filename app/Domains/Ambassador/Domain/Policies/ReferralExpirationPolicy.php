<?php

namespace App\Domains\Ambassador\Domain\Policies;

use App\Domains\Ambassador\Infrastructure\Persistence\Models\Referral;

class ReferralExpirationPolicy
{
    public function isExpired(Referral $referral): bool
    {
        if ($referral->revoked_at !== null) {
            return true;
        }

        if ($referral->expires_at === null) {
            return false;
        }

        return $referral->expires_at->isPast();
    }
}
