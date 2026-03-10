<?php

namespace App\Policies;

use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class EvaluationSessionPolicy
{
    public function view(Identity $actor, EvaluationSession $session): bool
    {
        if ($session->identity_id === $actor->id) {
            return true;
        }

        // Un FieldAgent peut accéder à la session d'un bénéficiaire qu'il a créée
        $agentId = $session->context['agent_id'] ?? null;
        if ($agentId && (string) $agentId === (string) $actor->id) {
            return $actor->hasMembershipRole('FieldAgent');
        }

        return false;
    }
}
