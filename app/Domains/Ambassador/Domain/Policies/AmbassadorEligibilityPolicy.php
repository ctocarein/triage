<?php

namespace App\Domains\Ambassador\Domain\Policies;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class AmbassadorEligibilityPolicy
{
    public function canActivate(Identity $actor, Identity $target, ?string $organizationId = null): bool
    {
        if (! $actor->hasMembershipRole('PlatformAdmin')) {
            return false;
        }

        return $target->hasMembershipRole('Beneficiary')
            || $target->hasMembershipRole('Ambassador');
    }

    public function canDeactivate(Identity $actor): bool
    {
        return $actor->hasMembershipRole('Ambassador');
    }
}
