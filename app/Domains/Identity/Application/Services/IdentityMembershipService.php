<?php

namespace App\Domains\Identity\Application\Services;

use App\Domains\Access\Models\Membership;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class IdentityMembershipService
{
    public function assign(array $data, Identity $actor): Membership
    {
        $role = $data['role'];
        $organizationId = $data['organization_id'] ?? null;

        $this->assertAuthorization($actor, $role, $organizationId);

        $membership = Membership::firstOrCreate([
            'identity_id' => $data['identity_id'],
            'organization_id' => $organizationId,
            'role' => $role,
        ], [
            'status' => 'active',
        ]);

        if ($membership->status !== 'active') {
            $membership->forceFill(['status' => 'active'])->save();
        }

        return $membership->fresh(['organization:id,name']);
    }

    private function assertAuthorization(Identity $actor, string $role, ?string $organizationId): void
    {
        if ($actor->hasMembershipRole('PlatformAdmin')) {
            return;
        }

        if (! $organizationId || ! $actor->hasMembershipRole('OrgManager', $organizationId)) {
            abort(403, 'Forbidden.');
        }

        if (in_array($role, ['OrgManager', 'PlatformAdmin', 'System'], true)) {
            abort(403, 'Forbidden.');
        }
    }
}
