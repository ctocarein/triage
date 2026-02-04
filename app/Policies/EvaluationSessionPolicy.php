<?php

namespace App\Policies;

use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class EvaluationSessionPolicy
{
    public function view(Identity $actor, EvaluationSession $session): bool
    {
        return $session->identity_id === $actor->id;
    }
}
