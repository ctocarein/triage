<?php

namespace App\Evaluation\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EvaluationCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $evaluationId
    ) {}
}
