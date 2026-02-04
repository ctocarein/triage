<?php

namespace App\Domains\Ambassador\Application\Services;

use App\Domains\Ambassador\Domain\Events\AmbassadorActivated;
use App\Domains\Ambassador\Domain\Events\AmbassadorDeactivated;
use App\Domains\Ambassador\Domain\Policies\AmbassadorEligibilityPolicy;
use App\Domains\Ambassador\Domain\Repositories\AmbassadorRepository;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Ambassador;
use App\Domains\Ambassador\Application\Services\AmbassadorLevelService;
use App\Domains\Access\Models\Membership;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class AmbassadorActivationService
{
    public function __construct(
        private readonly AmbassadorRepository $repository,
        private readonly AmbassadorEligibilityPolicy $eligibilityPolicy,
        private readonly AmbassadorLevelService $levelService,
    ) {
    }

    public function activate(Identity $actor, Identity $target, ?string $organizationId): Ambassador
    {
        if (! $this->eligibilityPolicy->canActivate($actor, $target, $organizationId)) {
            abort(403, 'Selected identity is not eligible to be an ambassador.');
        }

        $platformMembership = Membership::query()
            ->where('identity_id', $actor->id)
            ->where('role', 'PlatformAdmin')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (! $platformMembership || ! $platformMembership->organization_id) {
            abort(403, 'PlatformAdmin must belong to an organization.');
        }

        $organizationId = $organizationId ?? $platformMembership->organization_id;

        $ambassadorMembership = Membership::query()
            ->where('identity_id', $target->id)
            ->where('role', 'Ambassador')
            ->where('organization_id', $organizationId)
            ->first();

        if (! $ambassadorMembership) {
            $hasBeneficiary = Membership::query()
                ->where('identity_id', $target->id)
                ->where('role', 'Beneficiary')
                ->where('status', 'active')
                ->exists();

            if (! $hasBeneficiary) {
                abort(403, 'Selected identity must be an active beneficiary.');
            }

            $ambassadorMembership = Membership::firstOrCreate([
                'identity_id' => $target->id,
                'organization_id' => $organizationId,
                'role' => 'Ambassador',
            ], [
                'status' => 'active',
            ]);
        }

        if ($ambassadorMembership->status !== 'active') {
            $ambassadorMembership->forceFill(['status' => 'active'])->save();
        }

        $ambassador = $this->repository->findByUserId($target->id);

        if ($ambassador === null) {
            $ambassador = $this->repository->createForUser($target->id);
        }

        $ambassador->forceFill([
            'status' => 'active',
            'activated_at' => now(),
            'deactivated_at' => null,
        ])->save();

        $this->levelService->ensureStarterLevel($ambassador);

        event(new AmbassadorActivated($ambassador->id, $target->id));

        return $ambassador;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function deactivate(Identity $actor, array $payload = []): Ambassador
    {
        if (! $this->eligibilityPolicy->canDeactivate($actor)) {
            abort(403, 'You are not allowed to deactivate this ambassador.');
        }

        $ambassador = $this->requireAmbassador($actor);

        $status = $payload['status'] ?? 'paused';
        if (! in_array($status, ['paused', 'exited'], true)) {
            $status = 'paused';
        }

        $ambassador->forceFill([
            'status' => $status,
            'deactivated_at' => now(),
        ])->save();

        event(new AmbassadorDeactivated($ambassador->id, $actor->id, $payload['reason'] ?? null));

        return $ambassador;
    }

    public function requireAmbassador(Identity $actor): Ambassador
    {
        $ambassador = $this->repository->findByUserId($actor->id);

        if ($ambassador === null) {
            abort(404, 'Ambassador profile not found.');
        }

        return $ambassador;
    }

    public function requireActiveAmbassador(Identity $actor): Ambassador
    {
        $ambassador = $this->requireAmbassador($actor);

        if ($ambassador->status !== 'active') {
            abort(403, 'Ambassador is not active.');
        }

        return $ambassador;
    }
}
