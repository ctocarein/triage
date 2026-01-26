<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;

class ScoreService
{
    public function updateScore(EvaluationSession $session, int $questionId, $skipped = false): void
    {
        if ($skipped) return;

        $question = DynamicQuestionTemplate::find($questionId);

        if ($question && $question->risk_weight > 0) {
            $session->increment('score', $question->risk_weight);
        }
    }
}
