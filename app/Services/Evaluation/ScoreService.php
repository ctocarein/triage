<?php

namespace App\Services\Evaluation;

use App\Models\Evaluation\EvaluationSession;
use App\Models\Evaluation\DynamicQuestionTemplate;

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
