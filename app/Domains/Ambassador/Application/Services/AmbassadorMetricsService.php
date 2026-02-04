<?php

namespace App\Domains\Ambassador\Application\Services;

use App\Domains\Ambassador\Infrastructure\Persistence\Models\Ambassador;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\AmbassadorLevel;

class AmbassadorMetricsService
{
    /**
     * @return array<string, mixed>
     */
    public function getMetrics(Ambassador $ambassador): array
    {
        $referrals = $ambassador->referrals();

        $totalReferrals = (clone $referrals)->count();
        $revokedReferrals = (clone $referrals)->whereNotNull('revoked_at')->count();
        $expiredReferrals = (clone $referrals)
            ->whereNull('revoked_at')
            ->where('expires_at', '<', now())
            ->count();
        $activeReferrals = (clone $referrals)
            ->whereNull('revoked_at')
            ->where('expires_at', '>=', now())
            ->count();

        $startedUsages = $ambassador->referralUsages()->where('event_type', 'started')->count();
        $completedUsages = $ambassador->referralUsages()->where('event_type', 'completed')->count();

        $lastUsedAt = $ambassador->referralUsages()->max('occurred_at');

        $badgesEarned = $ambassador->badges()->where('status', 'earned')->count();

        $level = AmbassadorLevel::query()->where('ambassador_id', $ambassador->id)->first();

        return [
            'total_referrals' => $totalReferrals,
            'active_referrals' => $activeReferrals,
            'expired_referrals' => $expiredReferrals,
            'revoked_referrals' => $revokedReferrals,
            'started_usages' => $startedUsages,
            'completed_usages' => $completedUsages,
            'last_used_at' => $lastUsedAt,
            'badges_earned' => $badgesEarned,
            'level_code' => $level?->level_code ?? 'starter',
        ];
    }
}
