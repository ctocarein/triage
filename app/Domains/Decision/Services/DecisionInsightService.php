<?php

namespace App\Domains\Decision\Services;

use App\Domains\Decision\Models\DecisionInsight;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Models\ModelVersion;
use App\Domains\Evaluation\Services\DataQualityService;
use App\Domains\Evaluation\Services\RiskModelService;
use App\Domains\Evaluation\Services\FactsEngine;
use App\Domains\Evaluation\Services\RiskEngine;
use App\Domains\Evaluation\Services\FlagEngine;
use App\Domains\Evaluation\Services\DataQualityEngine;
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
            'vnext_shadow' => $this->buildVnextShadow($session),
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
        $thresholds = config('decision-thresholds.confidence', []);
        $min = (float) ($thresholds['min'] ?? 0.7);
        $highBonus = (float) ($thresholds['high_bonus'] ?? 0.05);

        $base = $quality['data_quality_score'] ?? 1.0;
        if ($risk['risk_level'] === 'high') {
            $base = min(1.0, $base + $highBonus);
        }

        return round(max(0.0, min(1.0, $base)), 4);
    }

    protected function buildVnextShadow(EvaluationSession $session): ?array
    {
        if (! $this->isVnextShadowMode()) {
            return null;
        }

        $facts = app(FactsEngine::class)->evaluate($session);
        $risk = app(RiskEngine::class)->evaluate($session, $facts);
        $flags = app(FlagEngine::class)->evaluate($session, $facts);
        $quality = app(DataQualityEngine::class)->assess($session);

        return [
            'facts' => $facts,
            'risk' => $risk,
            'flags' => $flags,
            'confidence_score' => $quality['data_quality_score'] ?? null,
            'versions' => [
                'protocol' => $session->decision_path_key && $session->decision_path_version
                    ? $session->decision_path_key . '@' . $session->decision_path_version
                    : $session->decision_path_key,
                'risk_model_version_id' => $session->risk_model_version,
                'engine' => 'vnext_shadow',
            ],
        ];
    }

    protected function isVnextShadowMode(): bool
    {
        return (bool) config('vnext.shadow_mode', false);
    }

    protected function computeConfidenceInterval(float $riskScore, float $confidence): array
    {
        $thresholds = config('decision-thresholds.confidence', []);
        $minMargin = (float) ($thresholds['min_margin'] ?? 0.05);
        $marginFactor = (float) ($thresholds['margin_factor'] ?? 0.2);
        $margin = max($minMargin, $marginFactor * (1 - $confidence));
        $low = max(0.0, $riskScore - $margin);
        $high = min(1.0, $riskScore + $margin);

        return [round($low, 4), round($high, 4)];
    }

    protected function requiresHumanValidation(string $riskLevel, float $confidence, array $quality): bool
    {
        $qualityThresholds = config('decision-thresholds.quality', []);
        $reviewThresholds = config('decision-thresholds.review', []);
        $confidenceThresholds = config('decision-thresholds.confidence', []);

        $criticalMissingRequiresReview = (bool) ($qualityThresholds['critical_missing_requires_review'] ?? true);
        if ($criticalMissingRequiresReview && ($quality['critical_missing'] ?? false) === true) {
            return true;
        }

        $minConfidence = (float) ($confidenceThresholds['min'] ?? 0.7);
        if ($confidence < $minConfidence) {
            return true;
        }

        $highRiskRequiresReview = (bool) ($reviewThresholds['high_risk_requires_review'] ?? true);
        return $highRiskRequiresReview && $riskLevel === 'high';
    }

    protected function resolveHumanValidationReason(string $riskLevel, float $confidence, array $quality): ?string
    {
        $qualityThresholds = config('decision-thresholds.quality', []);
        $reviewThresholds = config('decision-thresholds.review', []);
        $confidenceThresholds = config('decision-thresholds.confidence', []);

        $criticalMissingRequiresReview = (bool) ($qualityThresholds['critical_missing_requires_review'] ?? true);
        if ($criticalMissingRequiresReview && ($quality['critical_missing'] ?? false) === true) {
            return 'missing_critical_answers';
        }

        $minQuality = (float) ($qualityThresholds['min'] ?? 0.6);
        if (($quality['data_quality_score'] ?? 1.0) < $minQuality) {
            return 'data_quality_low';
        }

        $minConfidence = (float) ($confidenceThresholds['min'] ?? 0.7);
        if ($confidence < $minConfidence) {
            return 'confidence_below_threshold';
        }

        $highRiskRequiresReview = (bool) ($reviewThresholds['high_risk_requires_review'] ?? true);
        if ($highRiskRequiresReview && $riskLevel === 'high') {
            return 'high_risk_requires_review';
        }

        return null;
    }

    protected function suggestedAction(string $riskLevel): string
    {
        $actions = config('decision-thresholds.suggested_action', []);
        return $actions[$riskLevel] ?? ($actions['default'] ?? 'self_care');
    }
}
