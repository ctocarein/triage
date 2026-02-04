<?php

namespace App\Integration\Application;

use App\Evaluation\Domain\Events\EvaluationCompleted;
use App\Shared\Outbox\Services\OutboxRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleEvaluationCompleted implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private readonly OutboxRecorder $outbox) {}

    public function handle(EvaluationCompleted $event): void
    {
        /**
         * IMPORTANT:
         * Ici tu récupères juste les champs nécessaires à l’intégration.
         * Remplace les lignes ci-dessous par tes vrais accès (EvaluationSession, Profile, Report…)
         */
        $payload = $this->buildPSMMinimalPayload($event->evaluationId);

        $this->outbox->record(
            type: 'EVALUATION_COMPLETED',
            aggregateId: $event->evaluationId,
            payload: $payload,
            version: 'v1'
        );
    }

    private function buildPSMMinimalPayload(string $evaluationId): array
    {
        // TODO: Remplacer par tes requêtes (EvaluationSession + Identity/Profile + Report)
        // Champs PSM obligatoires: nom, prenom, sexe
        // Champs PSM optionnels: contact, datenaissance, sensibilite, lieuhabitation, typeCancer

        return [
            'evaluation_id' => $evaluationId,
            'identity_id' => null, // si tu as identity
            'decision_level' => 'HIGH', // depuis Report/DecisionPath
            'consent_share_contact' => true, // depuis consentement
            'nom' => 'Doe',
            'prenom' => 'Jane',
            'sexe' => 'F',
            'contact' => '+22501020304',
            'datenaissance' => '12/03/1995',
            'sensibilite' => 'élevée',
            'lieuhabitation' => 'Abidjan',
            'typeCancer' => 'sein',
        ];
    }
}
