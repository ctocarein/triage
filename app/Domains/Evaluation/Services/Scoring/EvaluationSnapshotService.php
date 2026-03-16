<?php

namespace App\Domains\Evaluation\Services\Scoring;

use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Models\EvaluationSnapshot;
use App\Domains\Evaluation\Models\ModelVersion;
use App\Domains\Evaluation\Services\Insight\DataQualityService;

class EvaluationSnapshotService
{
    public function __construct(
        protected FactsEngine       $factsEngine,
        protected RiskEngine        $riskEngine,
        protected FlagEngine        $flagEngine,
        protected DataQualityService $dataQuality,
    ) {}

    public function maybeRecord(EvaluationSession $session): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $facts   = $this->factsEngine->evaluate($session);
        $risk    = $this->riskEngine->evaluate($session, $facts);
        $flags   = $this->flagEngine->evaluate($session, $facts);
        $quality = $this->dataQuality->assess($session);

        $versions = [
            'protocol'              => $this->resolveProtocolVersion($session),
            'risk_model_version_id' => $session->risk_model_version ?? $this->resolveActiveVersionId('risk_model'),
            'engine'                => 'facts_signals',
        ];

        $hash = hash('sha256', json_encode(compact('facts', 'risk', 'flags', 'versions')));

        EvaluationSnapshot::create([
            'evaluation_session_id' => $session->id,
            'facts'                 => $facts,
            'risk'                  => $risk,
            'flags'                 => $flags,
            'versions'              => $versions,
            'confidence_score'      => $quality['data_quality_score'] ?? null,
            'snapshot_hash'         => $hash,
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
