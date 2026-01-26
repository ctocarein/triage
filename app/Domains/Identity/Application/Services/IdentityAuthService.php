<?php

namespace App\Domains\Identity\Application\Services;

use App\Domains\Access\Models\Membership;
use App\Domains\Identity\Infrastructure\Persistence\Models\Credential;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Organization\Models\Organization;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class IdentityAuthService
{
    public function register(array $data): array
    {
        $credential = Credential::where('type', $data['credential_type'])
            ->where('identifier', $data['identifier'])
            ->first();

        if ($credential) {
            throw ValidationException::withMessages([
                'identifier' => ['Credential already exists.'],
            ]);
        }

        $identity = Identity::create([
            'kind' => $data['kind'] ?? 'person',
            'status' => 'active',
        ]);

        $credential = Credential::create([
            'identity_id' => $identity->id,
            'type' => $data['credential_type'],
            'identifier' => $data['identifier'],
            'secret_hash' => Hash::make($data['secret']),
            'verified_at' => now(),
        ]);

        $role = $data['role'] ?? ($identity->kind === 'system' ? 'System' : 'Beneficiary');
        $organizationId = $this->resolveOrganizationId($data, $role);
        $membership = Membership::create([
            'identity_id' => $identity->id,
            'organization_id' => $organizationId,
            'role' => $role,
            'status' => 'active',
        ]);

        $token = $identity->createToken('identity-token')->plainTextToken;

        return [
            'token' => $token,
            'identity' => $identity,
            'credential' => $credential,
            'membership' => $membership,
        ];
    }

    public function login(array $data): array
    {
        $credential = Credential::where('type', $data['credential_type'])
            ->where('identifier', $data['identifier'])
            ->first();

        if (! $credential || ! $credential->secret_hash) {
            throw ValidationException::withMessages([
                'identifier' => ['Credential not found.'],
            ]);
        }

        if (! Hash::check($data['secret'], $credential->secret_hash)) {
            throw ValidationException::withMessages([
                'secret' => ['Invalid secret.'],
            ]);
        }

        $identity = $credential->identity;

        if ($identity->status !== 'active') {
            throw ValidationException::withMessages([
                'identity' => ['Identity is not active.'],
            ]);
        }

        $credential->forceFill(['last_used_at' => now()])->save();

        $token = $identity->createToken('identity-token')->plainTextToken;

        return [
            'token' => $token,
            'identity' => $identity->load('memberships'),
        ];
    }

    private function resolveOrganizationId(array $data, string $role): ?string
    {
        if (! empty($data['organization_id'])) {
            return $data['organization_id'];
        }

        $organization = Organization::where('name', "Care'IN triage")
            ->orWhere('name', 'CareIN')
            ->first();

        return $organization?->id;
    }
}
