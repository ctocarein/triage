<?php

namespace App\Domains\Evaluation\Domain\Policies;

use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class IdentityEvaluationPolicy
{
    public static function hasCompletedFullEvaluation(Identity $identity): bool
    {
        return $identity->evaluationSessions()
            ->where('type', 'complete')
            ->where('status', 'completed')
            ->exists();
    }

    public static function getInProgressFullEvaluation(Identity $identity): ?EvaluationSession
    {
        return $identity->evaluationSessions()
            ->where('type', 'complete')
            ->where('status', 'in_progress')
            ->latest()
            ->first();
    }

    public static function canStartThematicEvaluation(Identity $identity): bool
    {
        return self::hasCompletedFullEvaluation($identity)
            && ! self::getInProgressFullEvaluation($identity);
    }

    public static function lastCompletedFullEvaluation(Identity $identity): ?EvaluationSession
    {
        return $identity->evaluationSessions()
            ->where('type', 'complete')
            ->where('status', 'completed')
            ->orderByDesc('completed_at')
            ->first();
    }

    public static function shouldSuggestFullEvaluation(Identity $identity, int $months = 6): bool
    {
        $last = self::lastCompletedFullEvaluation($identity);
        if (! $last) {
            return true;
        }

        return now()->diffInMonths($last->completed_at) >= $months;
    }
}
