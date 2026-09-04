<?php

namespace App\Domains\Evaluation\Domain\Policies;

use App\Domains\Access\Models\Membership;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class IdentityEvaluationPolicy
{
    /**
     * Roles autorises a conduire une evaluation pour le compte d'un beneficiaire.
     *
     * @var array<int, string>
     */
    public const EVALUATOR_ROLES = ['FieldAgent', 'Ambassador'];

    /**
     * L'acteur porte-t-il un role d'evaluateur actif, dans n'importe quelle organisation ?
     *
     * Sert de garde peu couteuse avant de resoudre le sujet : un acteur sans role
     * d'evaluateur n'a aucune raison de fournir un subject_id.
     */
    public static function isEvaluator(Identity $actor): bool
    {
        return $actor->memberships()
            ->where('status', 'active')
            ->whereIn('role', self::EVALUATOR_ROLES)
            ->exists();
    }

    /**
     * L'acteur peut-il conduire une evaluation pour ce sujet ?
     *
     * Exige que le role d'evaluateur soit porte dans une organisation ou le sujet
     * est lui-meme membre actif : un agent ne peut pas evaluer le beneficiaire
     * d'une autre organisation, meme s'il porte le role ailleurs.
     */
    public static function canConductAssistedEvaluation(Identity $actor, Identity $subject): bool
    {
        if ((string) $actor->id === (string) $subject->id) {
            return false;
        }

        return Membership::query()
            ->where('identity_id', $actor->id)
            ->where('status', 'active')
            ->whereIn('role', self::EVALUATOR_ROLES)
            ->whereIn('organization_id', Membership::query()
                ->select('organization_id')
                ->where('identity_id', $subject->id)
                ->where('status', 'active'))
            ->exists();
    }

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
            ->active()
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
