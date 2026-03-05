<?php

namespace App\Domains\Ambassador\Application\Services;

use App\Domains\Ambassador\Domain\Repositories\AmbassadorBadgeRepository;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Ambassador;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\AmbassadorBadge;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AmbassadorBadgeService
{
    public function __construct(private readonly AmbassadorBadgeRepository $repository)
    {
    }

    public function list(Ambassador $ambassador): Collection
    {
        return $this->repository->listForAmbassador($ambassador);
    }

    /**
     * Attribue le badge de bienvenue lors de l'activation du compte ambassadrice.
     */
    public function awardWelcomeBadge(Ambassador $ambassador): void
    {
        $this->awardBadge($ambassador, 'welcome', []);
    }

    /**
     * Attribue le badge de premier lien lors de la création d'un referral.
     */
    public function awardFirstLinkBadge(Ambassador $ambassador): void
    {
        $referralCount = $ambassador->referrals()->count();
        if ($referralCount >= 1) {
            $this->awardBadge($ambassador, 'first_link_created', [
                'referral_count' => $referralCount,
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recompute(Ambassador $ambassador): array
    {
        $usages = $ambassador->referralUsages();
        $startedCount = (clone $usages)->where('event_type', 'started')->count();
        $completedCount = (clone $usages)->where('event_type', 'completed')->count();

        // --- Activation ---
        $this->awardBadge($ambassador, 'welcome', []);

        // --- Referrals créés ---
        $referralCount = $ambassador->referrals()->count();
        if ($referralCount >= 1) {
            $this->awardBadge($ambassador, 'first_link_created', [
                'referral_count' => $referralCount,
            ]);
        }

        // --- Partages (clics sur le lien) ---
        if ($startedCount >= 1) {
            $this->awardBadge($ambassador, 'first_share', [
                'started_usages' => $startedCount,
            ]);
        }

        // --- Évaluations complétées ---
        if ($completedCount >= 1) {
            $this->awardBadge($ambassador, 'first_completion', [
                'completed_usages' => $completedCount,
            ]);
        }

        if ($completedCount >= 10) {
            $this->awardBadge($ambassador, 'top_10', [
                'completed_usages' => $completedCount,
            ]);
        }

        if ($completedCount >= 50) {
            $this->awardBadge($ambassador, 'top_50', [
                'completed_usages' => $completedCount,
            ]);
        }

        if ($completedCount >= 100) {
            $this->awardBadge($ambassador, 'top_100', [
                'completed_usages' => $completedCount,
            ]);
        }

        // --- Streaks ---
        if ($this->hasCompletedStreak($ambassador, 7)) {
            $this->awardBadge($ambassador, 'streak_7_days', [
                'streak_days' => 7,
            ]);
        }

        if ($this->hasCompletedStreak($ambassador, 30)) {
            $this->awardBadge($ambassador, 'streak_30_days', [
                'streak_days' => 30,
            ]);
        }

        return $ambassador->badges()->get()->map(function (AmbassadorBadge $badge) {
            return [
                'badge_code' => $badge->badge_code,
                'status' => $badge->status,
                'earned_at' => $badge->earned_at,
            ];
        })->all();
    }

    /**
     * @param array<string, mixed> $evidence
     */
    private function awardBadge(Ambassador $ambassador, string $code, array $evidence = []): void
    {
        $badge = AmbassadorBadge::query()
            ->firstOrNew(['ambassador_id' => $ambassador->id, 'badge_code' => $code]);

        if ($badge->status !== 'earned') {
            $badge->forceFill([
                'status' => 'earned',
                'earned_at' => $badge->earned_at ?? now(),
                'revoked_at' => null,
                'evidence' => $evidence ?: null,
            ])->save();
        }
    }

    private function hasCompletedStreak(Ambassador $ambassador, int $days): bool
    {
        $dates = $ambassador->referralUsages()
            ->where('event_type', 'completed')
            ->selectRaw('DATE(occurred_at) as day')
            ->distinct()
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => Carbon::parse($row->day));

        if ($dates->count() < $days) {
            return false;
        }

        $streak = 1;
        $previous = null;

        foreach ($dates as $date) {
            if ($previous !== null) {
                $diff = $previous->diffInDays($date);
                if ($diff === 1) {
                    $streak++;
                } elseif ($diff > 1) {
                    $streak = 1;
                }
            }

            if ($streak >= $days) {
                return true;
            }

            $previous = $date;
        }

        return false;
    }
}
