<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Evaluation\Concerns\ResolvesRiskModel;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Models\RiskFlag;

class FlagEngine
{
    use ResolvesRiskModel;
    public function evaluate(EvaluationSession $session, array $facts): array
    {
        $riskModel = $this->resolveRiskModel();
        $modelVersionId = $this->resolveRiskModelVersionId($session);

        if (! $riskModel || ! $modelVersionId) {
            return [];
        }

        $flags = RiskFlag::query()
            ->where('risk_model_id', $riskModel->id)
            ->where('model_version_id', $modelVersionId)
            ->where('is_active', true)
            ->get();

        $hits = [];
        foreach ($flags as $flag) {
            if (!empty($facts[$flag->fact_key])) {
                $hits[] = [
                    'fact_key' => $flag->fact_key,
                    'type' => $flag->type,
                    'priority' => (int) $flag->priority,
                ];
            }
        }

        usort($hits, fn($a, $b) => $b['priority'] <=> $a['priority']);

        return $hits;
    }

}