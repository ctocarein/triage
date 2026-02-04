<?php

namespace App\Domains\Ambassador\Infrastructure\Adapters;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class IdentityAdapter
{
    public function isAmbassador(Identity $identity): bool
    {
        return $identity->hasMembershipRole('Ambassador');
    }
}
