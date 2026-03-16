<?php

namespace App\Domains\Evaluation\Services\Scoring;

use App\Domains\Evaluation\Enums\AnswerStatus;
use App\Domains\Evaluation\Enums\QuestionType;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\EvaluationAnswer;
use App\Domains\Evaluation\Models\EvaluationSession;

class RiskModelService
{
    public function __construct(
        protected FactsEngine $factsEngine,
        protected RiskEngine  $riskEngine,
    ) {}

    /**
     * Évalue le risque pour une session.
     *
     * Stratégie :
     * 1. Primaire  — FactsEngine → faits, RiskEngine → score via signaux configurés
     * 2. Fallback  — poids directs sur question.risk_weight (toujours disponible)
     */
    public function evaluate(EvaluationSession $session): array
    {
        $facts  = $this->factsEngine->evaluate($session);
        $result = $this->riskEngine->evaluate($session, $facts);

        if ($result['max_score'] > 0) {
            return $result;
        }

        return $this->evaluateDirectWeights($session);
    }

    /**
     * Calcul de score par poids directs (legacy).
     * Exposé pour usage dans le shadow mode comparatif.
     */
    public function evaluateDirectWeights(EvaluationSession $session): array
    {
        $answers = $session->answers()->with('question')->get();

        $scoreTotal = 0.0;
        foreach ($answers as $answer) {
            if (! $this->shouldCountAnswer($answer)) {
                continue;
            }

            $scoreTotal += $this->resolveAnswerWeight($answer);
        }

        $maxScore  = $this->resolveMaxScore($session);
        $riskScore = $maxScore > 0 ? min(1, $scoreTotal / $maxScore) : 0.0;

        $riskLevel = 'low';
        foreach (config('risk-level.levels') as $zone) {
            if ($scoreTotal >= $zone['min'] && $scoreTotal <= $zone['max']) {
                $riskLevel = $zone['name'];
                break;
            }
        }

        return [
            'risk_score'  => round($riskScore, 4),
            'risk_level'  => $riskLevel,
            'score_total' => round($scoreTotal, 2),
            'max_score'   => round($maxScore, 2),
        ];
    }

    public function shouldCountAnswer(EvaluationAnswer $answer): bool
    {
        if (in_array($answer->status, [AnswerStatus::Invalidated, AnswerStatus::NonApplicable], true)) {
            return false;
        }

        if ($answer->is_skipped || $answer->value === null) {
            return false;
        }

        $question = $answer->question;
        if (! $question) {
            return false;
        }

        if ($question->type === QuestionType::Boolean) {
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

        // Le max_score doit refléter le catalogue complet, pas seulement les réponses de la session.
        // Une session incomplète (early exit, abandon) ne doit pas produire un max_score sous-estimé
        // qui gonflerait artificiellement le risk_score normalisé.
        $catalogMax = (float) DynamicQuestionTemplate::where('is_active', true)
            ->where('risk_weight', '>', 0)
            ->sum('risk_weight');

        return max(1.0, $catalogMax);
    }

    protected function resolveAnswerWeight(EvaluationAnswer $answer): float
    {
        $weight = (float) ($answer->question?->risk_weight ?? 0);

        return $weight > 0 ? $weight : 0.0;
    }

    protected function isPositiveAnswer(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'oui', 'yes', 'true'], true);
    }
}
