<?php

namespace App\Domains\Ambassador\Application\Handlers;

use App\Domains\Ambassador\Application\Commands\RevokeReferralCommand;
use App\Domains\Ambassador\Application\Services\AmbassadorActivationService;
use App\Domains\Ambassador\Domain\Repositories\ReferralRepository;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Referral;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class RevokeReferralHandler
{
    public function __construct(
        private readonly ReferralRepository $referralRepository,
        private readonly AmbassadorActivationService $activationService,
    ) {
    }

    public function handle(Identity $actor, Referral $referral): Referral
    {
        $ambassador = $this->activationService->requireActiveAmbassador($actor);

        if ($referral->ambassador_id !== $ambassador->id) {
            abort(403, 'You are not allowed to revoke this referral.');
        }

        $command = new RevokeReferralCommand($actor, $referral);

        return $this->referralRepository->revoke($command->referral);
    }
}
