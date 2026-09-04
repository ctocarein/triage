<?php

namespace App\Policies;

use App\Domains\Evaluation\Domain\Policies\IdentityEvaluationPolicy;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class EvaluationSessionPolicy
{
    public function view(Identity $actor, EvaluationSession $session): bool
    {
        if ($session->identity_id === $actor->id) {
            return true;
        }

        // Un evaluateur accede a la session d'un beneficiaire qu'il a lui-meme demarree.
        // Le agent_id du contexte est declaratif : il ne suffit pas. On revalide que
        // l'acteur porte toujours un role d'evaluateur dans une organisation partagee
        // avec le sujet, sinon une session obtenue par un subject_id arbitraire
        // continuerait a ouvrir l'acces a ses donnees.
        $agentId = $session->context['agent_id'] ?? null;
        if (! $agentId || (string) $agentId !== (string) $actor->id) {
            return false;
        }

        $subject = $session->identity;

        return $subject !== null
            && IdentityEvaluationPolicy::canConductAssistedEvaluation($actor, $subject);
    }
}
