<?php

namespace App\Domains\ExternalApi\Integration\Domain\Policies;

use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\ExternalApi\Integration\Domain\Models\IntegrationLink;
use App\Domains\ExternalApi\Integration\Infrastructure\Repositories\IntegrationConfigRepository;

class ShouldSyncToPSM
{
    public function __construct(protected IntegrationConfigRepository $config)
    {
    }

    /**
     * @return array{allowed:bool,reason:?string}
     */
    public function evaluate(EvaluationSession $session): array
    {
        if (! $this->isEnabled()) {
            return ['allowed' => false, 'reason' => 'psm_disabled'];
        }

        if ($session->status !== 'completed') {
            return ['allowed' => false, 'reason' => 'session_not_completed'];
        }

        $personal = $session->identity?->personalProfile;
        if (! $personal) {
            return ['allowed' => false, 'reason' => 'missing_personal_profile'];
        }

        $missing = [];
        if (! $personal->last_name) {
            $missing[] = 'nom';
        }
        if (! $personal->first_name) {
            $missing[] = 'prenom';
        }
        if (! $personal->gender) {
            $missing[] = 'sexe';
        }

        if (! empty($missing)) {
            return ['allowed' => false, 'reason' => 'missing_required_fields:' . implode(',', $missing)];
        }

        $existing = IntegrationLink::query()
            ->where('provider', 'psm')
            ->where('local_type', 'evaluation_session')
            ->where('local_id', (string) $session->id)
            ->exists();

        if ($existing) {
            return ['allowed' => false, 'reason' => 'already_synced'];
        }

        // Point d'extension: ajouter ici des regles metier supplementaires.
        return ['allowed' => true, 'reason' => null];
    }

    protected function isEnabled(): bool
    {
        $dbEnabled = $this->config->get('psm', 'enabled');
        if ($dbEnabled !== null) {
            return filter_var($dbEnabled, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('services.psm.enabled', false);
    }
}


