<?php

namespace App\Domains\Evaluation\Concerns;

use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Models\ModelVersion;
use App\Domains\Evaluation\Models\RiskModel;

/**
 * Résolution du RiskModel actif et de la version de modèle applicable à une session.
 *
 * Partagé par RiskEngine et FlagEngine pour garantir qu'ils opèrent toujours
 * sur le même modèle et la même version — évitant une divergence silencieuse
 * entre le score et les alertes cliniques.
 */
trait ResolvesRiskModel
{
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
