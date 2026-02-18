<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Models\ModelVersion;
use App\Domains\Evaluation\Models\RiskModel;
use App\Domains\Evaluation\Models\RiskSignal;

class RiskEngine
{
    public function evaluate(EvaluationSession $session, array $facts): array
    {
        $riskModel = $this->resolveRiskModel();
        $modelVersionId = $this->resolveRiskModelVersionId($session);

        if (! $riskModel || ! $modelVersionId) {
            return [
                'risk_score' => 0.0,
                'risk_level' => 'low',
                'score_total' => 0.0,
                'max_score' => 0.0,
            ];
        }

        $signals = RiskSignal::query()
            ->where('risk_model_id', $riskModel->id)
            ->where('model_version_id', $modelVersionId)
            ->where('is_active', true)
            ->get();

        $scoreTotal = 0.0;
        $maxScore = 0.0;

        foreach ($signals as $signal) {
            $weight = (float) $signal->weight;
            if ($weight > 0) {
                $maxScore += $weight;
            }

            if (!empty($facts[$signal->fact_key])) {
                $scoreTotal += $weight;
            }
        }

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

    protected function resolveRiskModel(): ?RiskModel
    {
        return RiskModel::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
    }

    protected function resolveRiskModelVersionId(EvaluationSession $session): ?int
    {
        if ($session->risk_model_version) {
            return (int) $session->risk_model_version;
        }

        $default = ModelVersion::query()
            ->where('name', 'risk_model')
            ->where('is_active', true)
            ->orderByDesc('released_at')
            ->first();

        return $default?->id;
    }
}