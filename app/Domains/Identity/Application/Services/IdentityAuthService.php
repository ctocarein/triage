<?php

namespace App\Domains\Identity\Application\Services;

use App\Domains\Access\Models\Membership;
use App\Domains\Identity\Infrastructure\Persistence\Models\Credential;
use App\Domains\Identity\Infrastructure\Persistence\Models\IdentityDevice;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Identity\Infrastructure\Persistence\Models\IdentityPinAttempt;
use App\Domains\Identity\Infrastructure\Persistence\Models\IdentitySecurityGate;
use App\Domains\Identity\Infrastructure\Persistence\Models\IdentitySecurityEvent;
use App\Domains\Organization\Models\Organization;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class IdentityAuthService
{
    private const PIN_MAX_ATTEMPTS = 5;
    private const PIN_WINDOW_MINUTES = 10;
    private const PIN_COOLDOWN_MINUTES = 15;

    public function register(array $data): array
    {
        $secret = $data['secret'] ?? null;
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
            'secret_hash' => $secret === null ? null : Hash::make($secret),
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

        $securityGate = $this->resolveSecurityGate($identity);
        $device = $this->touchDevice($identity, $data['device_fingerprint'] ?? null);
        $state = $this->determineInitialGateState($data['credential_type'], $secret);
        if ($this->isGateLocked($securityGate)) {
            $state = 'locked';
        }
        if ($device && $device->trusted_at === null && $state === 'full') {
            $state = 'limited';
        }
        $this->updateGateState($securityGate, $state);

        $token = $identity->createToken('identity-token')->plainTextToken;

        return [
            'token' => $token,
            'identity' => $identity,
            'credential' => $credential,
            'membership' => $membership,
            'security_gate' => $this->buildSecurityPayload($securityGate, $device),
        ];
    }

    public function login(array $data): array
    {
        $type = $data['credential_type'];
        $identifier = $data['identifier'];
        $secret = $data['secret'] ?? null;
        $credential = Credential::where('type', $type)
            ->where('identifier', $identifier)
            ->first();

        if ($type === 'phone') {
            if (! $credential) {
                return $this->loginFromPhoneRegistration($data);
            }

            if ($credential->secret_hash) {
                if ($secret === null) {
                    throw ValidationException::withMessages([
                        'secret' => ['Secret is required.'],
                    ]);
                }

                if (! Hash::check($secret, $credential->secret_hash)) {
                    throw ValidationException::withMessages([
                        'secret' => ['Invalid secret.'],
                    ]);
                }
            }
        } else {
            if (! $credential || ! $credential->secret_hash) {
                throw ValidationException::withMessages([
                    'identifier' => ['Credential not found.'],
                ]);
            }

            if ($secret === null || ! Hash::check($secret, $credential->secret_hash)) {
                throw ValidationException::withMessages([
                    'secret' => ['Invalid secret.'],
                ]);
            }
        }

        $identity = $credential->identity;

        if (in_array($identity->status, ['email_unverified', 'pending_validation'], true)) {
            abort(423, 'Votre compte est en attente de validation.');
        }

        if ($identity->status !== 'active') {
            throw ValidationException::withMessages([
                'identity' => ['Identity is not active.'],
            ]);
        }

        $credential->forceFill(['last_used_at' => now()])->save();

        $securityGate = $this->resolveSecurityGate($identity);
        $device = $this->touchDevice($identity, $data['device_fingerprint'] ?? null);
        $state = $this->determineLoginGateState($type, $credential);
        if ($this->isGateLocked($securityGate)) {
            $state = 'locked';
        }
        if ($device && $device->trusted_at === null && $state === 'full') {
            $state = 'limited';
        }
        $this->updateGateState($securityGate, $state);

        $token = $identity->createToken('identity-token')->plainTextToken;

        return [
            'token'                => $token,
            'identity'             => $identity->load('memberships'),
            'security_gate'        => $this->buildSecurityPayload($securityGate, $device),
            'must_change_password' => (bool) ($credential->must_change_password ?? false),
            'is_new'               => false,
        ];
    }

    public function setPhoneSecret(Identity $identity, string $secret): Credential
    {
        $credential = $identity->credentials()
            ->where('type', 'phone')
            ->first();

        if (! $credential) {
            throw ValidationException::withMessages([
                'secret' => ['Phone credential not found.'],
            ]);
        }

        if ($credential->secret_hash) {
            throw ValidationException::withMessages([
                'secret' => ['Secret already set.'],
            ]);
        }

        $credential->forceFill(['secret_hash' => Hash::make($secret)])->save();

        return $credential->fresh();
    }

    public function unlockSecurityGate(Identity $identity, array $data): array
    {
        $gate = $this->resolveSecurityGate($identity);

        if ($this->isGateLocked($gate)) {
            throw ValidationException::withMessages([
                'gate' => ['Security gate is locked. Please wait before retrying.'],
            ]);
        }

        $method = $data['method'];
        $device = $this->touchDevice($identity, $data['device_fingerprint'] ?? null);

        if ($method === 'pin') {
            $credential = $identity->credentials()
                ->where('type', 'phone')
                ->first();

            if (! $credential || ! $credential->secret_hash) {
                throw ValidationException::withMessages([
                    'secret' => ['PIN not set.'],
                ]);
            }

            $secret = $data['secret'] ?? null;
            if (! $secret || ! Hash::check($secret, $credential->secret_hash)) {
                $this->recordPinAttempt($identity, $device, false);
                $this->applyCooldownIfNeeded($gate, $identity);

                throw ValidationException::withMessages([
                    'secret' => ['Invalid PIN.'],
                ]);
            }

            $this->recordPinAttempt($identity, $device, true);
        }

        $this->updateGateState($gate, 'full');
        $gate->forceFill(['last_unlock_method' => $method])->save();

        if ($method === 'pin' && $device && $device->trusted_at === null) {
            $device->forceFill(['trusted_at' => now()])->save();
        }

        $this->recordSecurityEvent($identity, 'unlock', [
            'method' => $method,
            'device_id' => $device?->id,
        ]);

        return [
            'security_gate' => $this->buildSecurityPayload($gate, $device),
        ];
    }

    public function panicLock(Identity $identity): array
    {
        $gate = $this->resolveSecurityGate($identity);

        $gate->forceFill([
            'state' => 'locked',
            'panic_locked_at' => now(),
        ])->save();

        IdentityDevice::query()
            ->where('identity_id', $identity->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $this->recordSecurityEvent($identity, 'panic_lock');

        return [
            'security_gate' => $this->buildSecurityPayload($gate, null),
        ];
    }

    public function getSecurityStatus(Identity $identity, ?string $fingerprint): array
    {
        $gate = $this->resolveSecurityGate($identity);
        $device = $this->findDevice($identity, $fingerprint);

        return [
            'security_gate' => $this->buildSecurityPayload($gate, $device),
        ];
    }

    private function loginFromPhoneRegistration(array $data): array
    {
        $payload = $this->register($data);
        $payload['credential']->forceFill(['last_used_at' => now()])->save();

        return [
            'token' => $payload['token'],
            'identity' => $payload['identity']->load('memberships'),
            'security_gate' => $payload['security_gate'] ?? null,
            'is_new' => true,
        ];
    }

    private function resolveOrganizationId(array $data, string $role): ?string
    {
        if (! empty($data['organization_id'])) {
            return $data['organization_id'];
        }

        $organization = Organization::query()
            ->whereHas('memberships', function ($query) {
                $query->where('role', 'PlatformAdmin')
                    ->where('status', 'active');
            })
            ->first();

        if (! $organization) {
            $organization = Organization::where('name', "Care'IN triage")
                ->orWhere('name', 'CareIN')
                ->first();
        }

        return $organization?->id;
    }

    private function resolveSecurityGate(Identity $identity): IdentitySecurityGate
    {
        return IdentitySecurityGate::firstOrCreate([
            'identity_id' => $identity->id,
        ], [
            'state' => 'entry',
        ]);
    }

    private function touchDevice(Identity $identity, ?string $fingerprint): ?IdentityDevice
    {
        if (! $fingerprint) {
            return null;
        }

        $device = IdentityDevice::firstOrCreate([
            'identity_id' => $identity->id,
            'device_fingerprint' => $fingerprint,
        ]);

        $device->forceFill(['last_seen_at' => now()])->save();

        return $device;
    }

    private function findDevice(Identity $identity, ?string $fingerprint): ?IdentityDevice
    {
        if (! $fingerprint) {
            return null;
        }

        return IdentityDevice::query()
            ->where('identity_id', $identity->id)
            ->where('device_fingerprint', $fingerprint)
            ->first();
    }

    private function determineInitialGateState(string $type, ?string $secret): string
    {
        if ($type === 'email') {
            return 'full';
        }

        return $secret ? 'full' : 'limited';
    }

    private function determineLoginGateState(string $type, Credential $credential): string
    {
        if ($type === 'email') {
            return 'full';
        }

        return $credential->secret_hash ? 'full' : 'limited';
    }

    private function isGateLocked(IdentitySecurityGate $gate): bool
    {
        if ($gate->panic_locked_at !== null) {
            return true;
        }

        return $gate->locked_until !== null && $gate->locked_until->isFuture();
    }

    private function updateGateState(IdentitySecurityGate $gate, string $state): void
    {
        $payload = ['state' => $state];

        if ($state === 'full') {
            $payload['unlocked_at'] = now();
        }

        $gate->forceFill($payload)->save();
    }

    private function buildSecurityPayload(IdentitySecurityGate $gate, ?IdentityDevice $device): array
    {
        $isLocked = $this->isGateLocked($gate);
        $state = $isLocked ? 'locked' : $gate->state;

        $secretSet = Credential::where('identity_id', $gate->identity_id)
            ->where('type', 'phone')
            ->whereNotNull('secret_hash')
            ->exists();

        return [
            'state' => $state,
            'gate_required' => $state !== 'full',
            'locked_until' => $gate->locked_until,
            'panic_locked_at' => $gate->panic_locked_at,
            'device_trusted' => $device?->trusted_at !== null,
            'secret_set' => $secretSet,
            'unlocked_at' => $gate->unlocked_at,
        ];
    }

    private function recordPinAttempt(Identity $identity, ?IdentityDevice $device, bool $success): void
    {
        IdentityPinAttempt::create([
            'identity_id' => $identity->id,
            'device_id' => $device?->id,
            'success' => $success,
        ]);

        if (! $success) {
            $this->recordSecurityEvent($identity, 'pin_failed', [
                'device_id' => $device?->id,
            ]);
        }
    }

    private function applyCooldownIfNeeded(IdentitySecurityGate $gate, Identity $identity): void
    {
        $windowStart = now()->subMinutes(self::PIN_WINDOW_MINUTES);
        $failures = IdentityPinAttempt::query()
            ->where('identity_id', $identity->id)
            ->where('success', false)
            ->where('created_at', '>=', $windowStart)
            ->count();

        if ($failures >= self::PIN_MAX_ATTEMPTS) {
            $gate->forceFill([
                'state' => 'locked',
                'locked_until' => now()->addMinutes(self::PIN_COOLDOWN_MINUTES),
            ])->save();

            $this->recordSecurityEvent($identity, 'lockout', [
                'reason' => 'pin_attempts',
                'locked_until' => $gate->locked_until,
            ]);
        }
    }

    private function recordSecurityEvent(Identity $identity, string $type, array $metadata = []): void
    {
        IdentitySecurityEvent::create([
            'identity_id' => $identity->id,
            'type' => $type,
            'metadata' => $metadata,
        ]);
    }
}
