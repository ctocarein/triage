<?php

namespace App\Domains\Ambassador\Application\Handlers;

use App\Domains\Ambassador\Application\Commands\GenerateReferralCommand;
use App\Domains\Ambassador\Application\Commands\RevokeReferralCommand;
use App\Domains\Ambassador\Application\Services\AmbassadorActivationService;
use App\Domains\Ambassador\Domain\Repositories\ReferralRepository;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Referral;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use Illuminate\Support\Carbon;

class GenerateReferralHandler
{
    public function __construct(
        private readonly ReferralRepository $referralRepository,
        private readonly AmbassadorActivationService $activationService,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(Identity $actor, array $payload): Referral
    {
        $command = new GenerateReferralCommand($actor, $payload);
        $ambassador = $this->activationService->requireActiveAmbassador($command->actor);

        return $this->referralRepository->create($ambassador, [
            'channel' => $payload['channel'],
            'expires_at' => $payload['expires_at'],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    /**
     * @param array<string, mixed> $payload
     * @return array{referral: Referral, reused: bool}
     */
    public function handleSmartLink(Identity $actor, array $payload): array
    {
        $command = new GenerateReferralCommand($actor, $payload);
        $ambassador = $this->activationService->requireActiveAmbassador($command->actor);
        $channel = $payload['channel'];

        $activeReferral = Referral::query()
            ->where('ambassador_id', $ambassador->id)
            ->where('channel', $channel)
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->latest('created_at')
            ->first();

        if ($activeReferral !== null) {
            return [
                'referral' => $activeReferral,
                'reused' => true,
                'remaining_weekly' => null,
            ];
        }

        $weeklyLimit = (int) config('ambassador.referrals.weekly_limit', 25);
        $createdThisWeek = 0;
        if ($weeklyLimit > 0) {
            $createdThisWeek = Referral::query()
                ->where('ambassador_id', $ambassador->id)
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count();

            if ($createdThisWeek >= $weeklyLimit) {
                abort(429, 'Weekly referral creation limit reached.');
            }
        }

        $expiresAt = ! empty($payload['expires_at'])
            ? Carbon::parse($payload['expires_at'])
            : now()->addDays((int) config('ambassador.referrals.default_expires_days', 30));

        $referral = $this->referralRepository->create($ambassador, [
            'channel' => $channel,
            'expires_at' => $expiresAt,
        ]);

        $remainingWeekly = $weeklyLimit > 0
            ? max(0, $weeklyLimit - $createdThisWeek - 1)
            : null;

        return [
            'referral' => $referral,
            'reused' => false,
            'remaining_weekly' => $remainingWeekly,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handleRevoke(Identity $actor, Referral $referral): Referral
    {
        $command = new RevokeReferralCommand($actor, $referral);

        return $this->referralRepository->revoke($command->referral);
    }
}
