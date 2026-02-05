<?php

namespace App\Domains\ExternalApi\Evaluation\Domain\Events;

use App\Domains\Evaluation\Models\EvaluationSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EvaluationCompleted
{
    use Dispatchable, SerializesModels;

    public const TYPE = 'evaluation.completed';

    public function __construct(public EvaluationSession $session)
    {
    }
}


