<?php

namespace App\Domains\ExternalApi\Integration\Application;

use App\Domains\ExternalApi\Evaluation\Domain\Events\EvaluationCompleted;
use App\Domains\ExternalApi\Integration\Domain\Policies\ShouldSyncToPSM;
use App\Domains\ExternalApi\Shared\Outbox\Jobs\ProcessOutboxEvents;
use App\Domains\ExternalApi\Shared\Outbox\OutboxEvent;

class HandleEvaluationCompleted
{
    public function __construct(protected ShouldSyncToPSM $policy)
    {
    }

    public function handle(EvaluationCompleted $event): void
    {
        $session = $event->session->loadMissing('identity.personalProfile');
        $decision = $this->policy->evaluate($session);

        if (! $decision['allowed']) {
            return;
        }

        OutboxEvent::create([
            'aggregate_type' => 'evaluation_session',
            'aggregate_id' => (string) $session->id,
            'event_type' => EvaluationCompleted::TYPE,
            'payload' => [
                'evaluation_session_id' => $session->id,
            ],
            'available_at' => now(),
        ]);

        // Traitement async de l'outbox sans bloquer le flow utilisateur.
        ProcessOutboxEvents::dispatch();
    }
}



