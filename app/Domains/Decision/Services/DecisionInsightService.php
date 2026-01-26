<?php

namespace App\Domains\Decision\Services;

use App\Domains\Decision\Models\DecisionInsight;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Models\ModelVersion;
use App\Domains\Evaluation\Services\DataQualityService;
use App\Domains\Evaluation\Services\RiskModelService;
use App\Domains\Report\Models\Report;

class DecisionInsightService
{
    public function __construct(
        protected RiskModelService $riskModel,
        protected DataQualityService $dataQuality
    ) {
    }

    public function generate(EvaluationSession $session, ?Report $report = null): DecisionInsight
    {
        $risk = $this->riskModel->evaluate($session);
        $quality = $this->dataQuality->assess($session);
        $explanations = $this->buildExplanations($session);

        $confidence = $this->computeConfidence($risk, $quality);
        [$ciLow, $ciHigh] = $this->computeConfidenceInterval($risk['risk_score'], $confidence);

        $requiresHuman = $this->requiresHumanValidation($risk['risk_level'], $confidence, $quality);
        $humanReason = $this->resolveHumanValidationReason($risk['risk_level'], $confidence, $quality);

        $payload = [
            'evaluation_session_id' => $session->id,
            'report_id' => $report?->id,
            'identity_id' => $session->identity_id,
            'model_version' => $this->resolveModelVersion($session),
            'calibration_tag' => null,
            'risk_score' => $risk['risk_score'],
            'risk_level' => $risk['risk_level'],
            'score_total' => $risk['score_total'],
            'confidence_score' => $confidence,
            'confidence_interval_low' => $ciLow,
            'confidence_interval_high' => $ciHigh,
            'data_quality' => $quality,
            'explanations' => $explanations,
            'suggested_action' => $this->suggestedAction($risk['risk_level']),
            'requires_human_validation' => $requiresHuman,
            'human_validation_reason' => $humanReason,
            'final_decision' => null,
        ];

        return DecisionInsight::updateOrCreate(
            ['evaluation_session_id' => $session->id],
            $payload
        );
    }

    protected function resolveModelVersion(EvaluationSession $session): string
    {
        if ($session->risk_model_version) {
            $version = ModelVersion::find($session->risk_model_version);
            if ($version) {
                return $version->version;
            }

            return (string) $session->risk_model_version;
        }

        $default = ModelVersion::query()
            ->where('name', 'risk_model')
            ->where('is_active', true)
            ->orderByDesc('released_at')
            ->first();

        if ($default) {
            return $default->version;
        }

        return '1';
    }

    protected function buildExplanations(EvaluationSession $session): array
    {
        $answers = $session->answers()->with('question')->get();
        $items = [];
        $totalWeight = 0.0;

        foreach ($answers as $answer) {
            if (! $this->riskModel->shouldCountAnswer($answer)) {
                continue;
            }

            $weight = (float) ($answer->question?->risk_weight ?? 0);
            if ($weight <= 0) {
                continue;
            }

            $totalWeight += $weight;
            $items[] = [
                'feature' => $answer->question?->key,
                'category' => 'other',
                'value' => $answer->value,
                'raw_weight' => $weight,
                'direction' => $this->resolveDirection($answer->value),
            ];
        }

        if ($totalWeight <= 0) {
            return [];
        }

        $items = array_map(function ($item) use ($totalWeight) {
            $item['contribution'] = round($item['raw_weight'] / $totalWeight, 4);
            unset($item['raw_weight']);
            return $item;
        }, $items);

        usort($items, fn($a, $b) => $b['contribution'] <=> $a['contribution']);

        return array_slice($items, 0, 5);
    }

    protected function resolveDirection(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'increases_risk' : 'decreases_risk';
        }

        if (is_numeric($value)) {
            return $value > 0 ? 'increases_risk' : 'neutral';
        }

        return 'neutral';
    }

    protected function computeConfidence(array $risk, array $quality): float
    {
        $base = $quality['data_quality_score'] ?? 1.0;
        if ($risk['risk_level'] === 'high') {
            $base = min(1.0, $base + 0.05);
        }

        return round(max(0.0, min(1.0, $base)), 4);
    }

    protected function computeConfidenceInterval(float $riskScore, float $confidence): array
    {
        $margin = max(0.05, 0.2 * (1 - $confidence));
        $low = max(0.0, $riskScore - $margin);
        $high = min(1.0, $riskScore + $margin);

        return [round($low, 4), round($high, 4)];
    }

    protected function requiresHumanValidation(string $riskLevel, float $confidence, array $quality): bool
    {
        if (($quality['critical_missing'] ?? false) === true) {
            return true;
        }

        if ($confidence < 0.7) {
            return true;
        }

        return $riskLevel === 'high';
    }

    protected function resolveHumanValidationReason(string $riskLevel, float $confidence, array $quality): ?string
    {
        if (($quality['critical_missing'] ?? false) === true) {
            return 'missing_critical_answers';
        }

        if (($quality['data_quality_score'] ?? 1.0) < 0.6) {
            return 'data_quality_low';
        }

        if ($confidence < 0.7) {
            return 'confidence_below_threshold';
        }

        if ($riskLevel === 'high') {
            return 'high_risk_requires_review';
        }

        return null;
    }

    protected function suggestedAction(string $riskLevel): string
    {
        return match ($riskLevel) {
            'high' => 'refer_to_specialist',
            'moderate' => 'medical_check',
            default => 'self_care',
        };
    }
}
