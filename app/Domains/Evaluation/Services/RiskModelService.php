<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Decision\Models\DecisionNode;
use App\Domains\Evaluation\Models\EvaluationAnswer;
use App\Domains\Evaluation\Models\EvaluationSession;

class RiskModelService
{
    public function evaluate(EvaluationSession $session): array
    {
        $answers = $session->answers()->with('question')->get();

        $scoreTotal = 0.0;
        foreach ($answers as $answer) {
            if (! $this->shouldCountAnswer($answer)) {
                continue;
            }

            $weight = $this->resolveAnswerWeight($answer);
            $scoreTotal += $weight;
        }

        $maxScore = $this->resolveMaxScore($session);
        $riskScore = $maxScore > 0 ? min(1, $scoreTotal / $maxScore) : 0.0;

        $riskLevel = 'low';
        foreach (config('risk-level.levels') as $zone) {
            if ($scoreTotal >= $zone['min'] && $scoreTotal <= $zone['max']) {
                $riskLevel = $zone['name'];
                break;
            }
        }

        return [
            'risk_score' => round($riskScore, 4),
            'risk_level' => $riskLevel,
            'score_total' => round($scoreTotal, 2),
            'max_score' => round($maxScore, 2),
        ];
    }

    protected function resolveMaxScore(EvaluationSession $session): float
    {
        $decisionPath = $session->decisionPath;
        if ($decisionPath) {
            $nodes = $decisionPath->nodes()->with('question')->get();
            $total = 0.0;
            foreach ($nodes as $node) {
                $weight = $node->risk_weight;
                if ($weight <= 0 && $node->question) {
                    $weight = $node->question->risk_weight ?? 0;
                }
                if ($weight > 0) {
                    $total += $weight;
                }
            }
            if ($total > 0) {
                return $total;
            }
        }

        $fallback = $session->answers()->with('question')->get()
            ->sum(fn($answer) => max(0, $answer->question?->risk_weight ?? 0));

        return max(1, (float) $fallback);
    }

    public function shouldCountAnswer(EvaluationAnswer $answer): bool
    {
        if ($answer->is_skipped || $answer->value === null) {
            return false;
        }

        $question = $answer->question;
        if (! $question) {
            return false;
        }

        if ($question->type === 'boolean') {
            return $this->isPositiveAnswer($answer->value);
        }

        if (is_array($answer->value)) {
            return count($answer->value) > 0;
        }

        if (is_string($answer->value)) {
            return trim($answer->value) !== '';
        }

        return true;
    }

    protected function resolveAnswerWeight(EvaluationAnswer $answer): float
    {
        $weight = $answer->question?->risk_weight ?? 0;
        $weight = (float) $weight;

        return $weight > 0 ? $weight : 0.0;
    }

    protected function isPositiveAnswer(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'oui', 'yes', 'true'], true);
    }
}
