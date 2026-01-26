<?php

namespace App\Policies;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Organization\Models\Organization;

class OrganizationPolicy
{
    public function viewAny(Identity $actor): bool
    {
        return $actor->hasMembershipRole('PlatformAdmin')
            || $actor->hasMembershipRole('OrgManager');
    }

    public function view(Identity $actor, Organization $organization): bool
    {
        return $actor->hasMembershipRole('PlatformAdmin')
            || $actor->hasMembershipRole('OrgManager', $organization->id);
    }

    public function create(Identity $actor): bool
    {
        return $actor->hasMembershipRole('PlatformAdmin');
    }

    public function update(Identity $actor, Organization $organization): bool
    {
        return $actor->hasMembershipRole('PlatformAdmin')
            || $actor->hasMembershipRole('OrgManager', $organization->id);
    }

    public function delete(Identity $actor, Organization $organization): bool
    {
        return $actor->hasMembershipRole('PlatformAdmin');
    }
}
