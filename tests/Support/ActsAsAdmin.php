<?php

namespace Tests\Support;

use App\Domains\Access\Models\Membership;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

trait ActsAsAdmin
{
    protected function adminIdentity(): Identity
    {
        $identity = Identity::factory()->create(['kind' => 'person', 'status' => 'active']);

        Membership::create([
            'identity_id' => $identity->id,
            'role'        => 'PlatformAdmin',
            'status'      => 'active',
        ]);

        return $identity;
    }

    protected function actingAsAdmin(): static
    {
        return $this->actingAs($this->adminIdentity(), 'sanctum');
    }
}
