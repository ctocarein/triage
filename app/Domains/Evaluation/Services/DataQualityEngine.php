<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Evaluation\Models\EvaluationSession;

class DataQualityEngine
{
    public function assess(EvaluationSession $session): array
    {
        $totalQuestions = $this->resolveTotalQuestions($session);
        $answeredCount = $session->answers()->count();

        $missing = max(0, $totalQuestions - $answeredCount);
        $dataQualityScore = $totalQuestions > 0
            ? round($answeredCount / $totalQuestions, 4)
            : 1.0;

        $criticalMissing = $totalQuestions > 0
            ? ($missing / $totalQuestions) >= 0.3
            : false;

        return [
            'missing_answers' => $missing,
            'critical_missing' => $criticalMissing,
            'inconsistent' => false,
            'data_quality_score' => $dataQualityScore,
            'notes' => [],
        ];
    }

    protected function resolveTotalQuestions(EvaluationSession $session): int
    {
        $decisionPath = $session->decisionPath;
        if ($decisionPath) {
            $nodes = $decisionPath->nodes()->whereNotNull('question_id')->count();
            if ($nodes > 0) {
                return $nodes;
            }
        }

        return $session->answers()->count();
    }
}