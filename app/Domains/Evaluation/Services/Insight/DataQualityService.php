<?php

namespace App\Domains\Evaluation\Services\Insight;

use App\Domains\Evaluation\Enums\AnswerStatus;
use App\Domains\Evaluation\Models\EvaluationSession;

class DataQualityService
{
    public function assess(EvaluationSession $session, bool $excludeNonApplicable = true): array
    {
        $totalQuestions = $this->resolveTotalQuestions($session);
        $answeredCount = $session->answers()
            ->whereIn('status', [AnswerStatus::Answered->value, AnswerStatus::Skipped->value])
            ->count();

        $nonApplicable = $excludeNonApplicable
            ? $session->answers()->where('status', AnswerStatus::NonApplicable->value)->count()
            : 0;

        $expectedTotal = max(0, $totalQuestions - $nonApplicable);

        $missing = max(0, $expectedTotal - $answeredCount);
        $dataQualityScore = $expectedTotal > 0
            ? round($answeredCount / $expectedTotal, 4)
            : 1.0;

        $criticalMissing = $expectedTotal > 0
            ? ($missing / $expectedTotal) >= 0.3
            : false;

        return [
            'missing_answers' => $missing,
            'non_applicable' => $nonApplicable,
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
