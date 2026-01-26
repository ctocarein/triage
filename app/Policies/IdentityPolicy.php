<?php

namespace App\Policies;

use App\Domains\Access\Models\Membership;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class IdentityPolicy
{
    public function viewAny(Identity $actor): bool
    {
        return $actor->hasMembershipRole('PlatformAdmin')
            || $actor->hasMembershipRole('OrgManager');
    }

    public function view(Identity $actor, Identity $identity): bool
    {
        if ($actor->id === $identity->id || $actor->hasMembershipRole('PlatformAdmin')) {
            return true;
        }

        $orgIds = $actor->memberships()
            ->where('role', 'OrgManager')
            ->pluck('organization_id')
            ->filter()
            ->all();

        if (empty($orgIds)) {
            return false;
        }

        return Membership::query()
            ->where('identity_id', $identity->id)
            ->whereIn('organization_id', $orgIds)
            ->exists();
    }

    public function create(Identity $actor): bool
    {
        return $actor->hasMembershipRole('PlatformAdmin');
    }

    public function update(Identity $actor, Identity $identity): bool
    {
        return $actor->hasMembershipRole('PlatformAdmin');
    }

    public function delete(Identity $actor, Identity $identity): bool
    {
        return $actor->hasMembershipRole('PlatformAdmin');
    }
}
