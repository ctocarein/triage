<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Models\EvaluationSnapshot;
use App\Domains\Evaluation\Models\ModelVersion;

class EvaluationSnapshotService
{
    public function maybeRecord(EvaluationSession $session): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $facts = app(FactsEngine::class)->evaluate($session);
        $risk = app(RiskEngine::class)->evaluate($session, $facts);
        $flags = app(FlagEngine::class)->evaluate($session, $facts);
        $quality = app(DataQualityService::class)->assess($session);

        $snapshot = [
            'facts' => $facts,
            'risk' => $risk,
            'flags' => $flags,
            'versions' => [
                'protocol' => $this->resolveProtocolVersion($session),
                'risk_model_version_id' => $session->risk_model_version ?? $this->resolveActiveVersionId('risk_model'),
                'engine' => 'vnext_shadow',
            ],
            'confidence_score' => $quality['data_quality_score'] ?? null,
        ];

        $hash = hash('sha256', json_encode($snapshot));

        EvaluationSnapshot::create([
            'evaluation_session_id' => $session->id,
            'facts' => $facts,
            'risk' => $risk,
            'flags' => $flags,
            'versions' => $snapshot['versions'],
            'confidence_score' => $snapshot['confidence_score'],
            'snapshot_hash' => $hash,
        ]);
    }

    public function isEnabled(): bool
    {
        return (bool) config('vnext.shadow_mode', false);
    }

    public function resolveProtocolVersion(EvaluationSession $session): ?string
    {
        if ($session->decision_path_key && $session->decision_path_version) {
            return $session->decision_path_key . '@' . $session->decision_path_version;
        }

        return $session->decision_path_key ?: null;
    }

    protected function resolveActiveVersionId(string $name): ?int
    {
        $version = ModelVersion::query()
            ->where('name', $name)
            ->where('is_active', true)
            ->orderByDesc('released_at')
            ->first();

        return $version?->id;
    }
}
