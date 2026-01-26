<?php

namespace App\Policies;

use App\Domains\Access\Models\Membership;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class MembershipPolicy
{
    public function viewAny(Identity $actor): bool
    {
        return $actor->hasMembershipRole('PlatformAdmin')
            || $actor->hasMembershipRole('OrgManager');
    }

    public function view(Identity $actor, Membership $membership): bool
    {
        return $actor->hasMembershipRole('PlatformAdmin')
            || $actor->hasMembershipRole('OrgManager', $membership->organization_id);
    }

    public function create(Identity $actor, Membership $membership): bool
    {
        if ($actor->hasMembershipRole('PlatformAdmin')) {
            return true;
        }

        if (! $membership->organization_id) {
            return false;
        }

        if (! $actor->hasMembershipRole('OrgManager', $membership->organization_id)) {
            return false;
        }

        return ! in_array($membership->role, ['OrgManager', 'PlatformAdmin', 'System'], true);
    }

    public function update(Identity $actor, Membership $membership): bool
    {
        if ($actor->hasMembershipRole('PlatformAdmin')) {
            return true;
        }

        if (! $membership->organization_id) {
            return false;
        }

        if (! $actor->hasMembershipRole('OrgManager', $membership->organization_id)) {
            return false;
        }

        return ! in_array($membership->role, ['OrgManager', 'PlatformAdmin', 'System'], true);
    }

    public function delete(Identity $actor, Membership $membership): bool
    {
        if ($actor->hasMembershipRole('PlatformAdmin')) {
            return true;
        }

        if (! $membership->organization_id) {
            return false;
        }

        if (! $actor->hasMembershipRole('OrgManager', $membership->organization_id)) {
            return false;
        }

        return ! in_array($membership->role, ['OrgManager', 'PlatformAdmin', 'System'], true);
    }
}
