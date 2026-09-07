<?php

namespace App\Domains\ExternalApi\Integration\Infrastructure\Jobs;

use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Credential;
use App\Domains\ExternalApi\Integration\Domain\Models\IntegrationJob;
use App\Domains\ExternalApi\Integration\Domain\Models\IntegrationLink;
use App\Domains\ExternalApi\Integration\Domain\Policies\ShouldSyncToPSM;
use App\Domains\ExternalApi\Integration\Infrastructure\Connectors\PSMConnector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncEvaluationToPSM implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;
    public int $tries = 3;

    public function __construct(protected int $integrationJobId)
    {
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(
        PSMConnector $connector,
        ShouldSyncToPSM $policy
    ): void {
        $job = IntegrationJob::find($this->integrationJobId);
        if (! $job) {
            return;
        }

        $job->markProcessing();

        $sessionId = $job->payload['evaluation_session_id'] ?? null;
        if (! $sessionId) {
            $job->markFailed('Missing evaluation_session_id in integration job payload.');
            return;
        }

        $session = EvaluationSession::query()
            ->with(['identity.personalProfile', 'identity.medicalProfile'])
            ->find($sessionId);

        if (! $session) {
            $job->markFailed('Evaluation session not found.');
            return;
        }

        $decision = $policy->evaluate($session);
        if (! $decision['allowed']) {
            $job->markSkipped($decision['reason'] ?? 'sync_not_allowed');
            return;
        }

        $payload = $this->buildPayload($session);
        if ($payload['missing_required'] ?? false) {
            $job->markSkipped('missing_required_fields');
            return;
        }

        try {
            $response = $connector->createPatient($payload['data']);
        } catch (\Throwable $e) {
            $job->markFailed('psm_connection_error');
            Log::warning('PSM connection error.', ['error' => $e->getMessage()]);
            throw $e;
        }

        $status = $response->status();
        $body = $response->json() ?? [];

        if ($status >= 200 && $status < 300) {
            $identifier = $body['identifiant'] ?? null;
            $pin = $body['pin'] ?? null;

            $link = IntegrationLink::firstOrNew([
                'provider' => 'psm',
                'local_type' => 'evaluation_session',
                'local_id' => (string) $session->id,
            ]);
            $link->external_id = $identifier;
            $link->external_payload = [
                'identifiant' => $identifier,
            ];
            if ($pin) {
                $link->setPsmPin((string) $pin);
            }
            $link->save();

            $job->markSuccess([
                'status' => $status,
                'identifiant' => $identifier,
            ]);
            return;
        }

        if ($status >= 500 || $status === 429) {
            $job->markFailed('psm_server_error', ['status' => $status, 'body' => $body]);
            throw new \RuntimeException('PSM server error: ' . $status);
        }

        if ($status >= 400 && $status < 500) {
            $job->markFailed('psm_client_error', ['status' => $status, 'body' => $body]);
            return;
        }

        $job->markFailed('psm_unexpected_response', ['status' => $status, 'body' => $body]);
    }

    /**
     * @return array{missing_required:bool,data:array<string,mixed>}
     */
    protected function buildPayload(EvaluationSession $session): array
    {
        $personal = $session->identity?->personalProfile;
        $medical = $session->identity?->medicalProfile;

        $nom = $personal?->last_name;
        $prenom = $personal?->first_name;
        $sexe = $this->normalizeSex($personal?->gender);

        if (! $nom || ! $prenom || ! $sexe) {
            return ['missing_required' => true, 'data' => []];
        }

        $contact = $this->resolveContact($session);
        $typeCancer = $this->resolveCancerType($medical);

        $data = [
            'nom' => $nom,
            'prenom' => $prenom,
            'sexe' => $sexe,
            'contact' => $contact,
            'datenaissance' => $personal?->birth_date?->format('Y-m-d'),
            'sensibilite' => null,
            'lieuhabitation' => $personal?->municipality,
            'typeCancer' => $typeCancer,
        ];

        // Minimisation: envoyer uniquement les champs supportes par PSM.
        $allowed = ['nom', 'prenom', 'sexe', 'contact', 'datenaissance', 'sensibilite', 'lieuhabitation', 'typeCancer'];
        $payload = array_filter(
            $data,
            fn($value, $key) => in_array($key, $allowed, true) && $value !== null && $value !== '',
            ARRAY_FILTER_USE_BOTH
        );

        return ['missing_required' => false, 'data' => $payload];
    }

    protected function normalizeSex(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $normalized = strtolower(trim($value));
        return match ($normalized) {
            'm', 'male', 'homme' => 'M',
            'f', 'female', 'femme' => 'F',
            default => null,
        };
    }

    protected function resolveContact(EvaluationSession $session): ?string
    {
        $identityId = $session->identity_id;

        $phone = Credential::query()
            ->where('identity_id', $identityId)
            ->where('type', 'phone')
            ->orderByDesc('verified_at')
            ->value('identifier');

        if ($phone) {
            return $phone;
        }

        return Credential::query()
            ->where('identity_id', $identityId)
            ->where('type', 'email')
            ->orderByDesc('verified_at')
            ->value('identifier');
    }

    protected function resolveCancerType($medical): ?string
    {
        if (! $medical) {
            return null;
        }

        if ($medical->has_breast_cancer) {
            return 'breast';
        }
        if ($medical->has_cervical_cancer) {
            return 'cervical';
        }
        if ($medical->has_ovarian_cancer) {
            return 'ovarian';
        }
        if ($medical->has_other_cancer) {
            return 'other';
        }

        return null;
    }
}


