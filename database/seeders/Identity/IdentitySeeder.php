<?php

namespace Database\Seeders\Identity;

use App\Domains\Access\Models\Membership;
use App\Domains\Identity\Infrastructure\Persistence\Models\Credential;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Organization\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class IdentitySeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = env('SEED_ADMIN_EMAIL', 'superadmin@chereh.test');
        $adminPassword = env('SEED_ADMIN_PASSWORD', 'password123');

        $organization = Organization::whereIn('name', ["Care'IN triage", 'CareIN'])->first();

        if (! $organization) {
            $organization = Organization::create([
                'name' => "Care'IN triage",
                'type' => 'platform',
                'status' => 'active',
            ]);
        } else {
            $organization->fill([
                'name' => "Care'IN triage",
                'type' => $organization->type ?? 'platform',
                'status' => $organization->status ?? 'active',
            ])->save();
        }

        $credential = Credential::where('type', 'email')
            ->where('identifier', $adminEmail)
            ->first();

        if (! $credential) {
            $identity = Identity::create([
                'kind' => 'person',
                'status' => 'active',
            ]);

            $credential = Credential::create([
                'identity_id' => $identity->id,
                'type' => 'email',
                'identifier' => $adminEmail,
                'secret_hash' => Hash::make($adminPassword),
            ]);
        }

        Membership::firstOrCreate([
            'identity_id' => $credential->identity_id,
            'organization_id' => $organization->id,
            'role' => 'PlatformAdmin',
        ], [
            'status' => 'active',
        ]);
    }
}
