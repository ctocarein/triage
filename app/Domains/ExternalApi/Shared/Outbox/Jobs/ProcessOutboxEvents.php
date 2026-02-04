<?php

namespace App\Domains\ExternalApi\Shared\Outbox\Jobs;

use App\Domains\ExternalApi\Integration\Domain\Models\IntegrationJob;
use App\Domains\ExternalApi\Integration\Infrastructure\Jobs\SyncEvaluationToPSM;
use App\Domains\ExternalApi\Shared\Outbox\OutboxEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessOutboxEvents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;
    public int $tries = 3;

    public function __construct(protected int $limit = 50)
    {
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        $events = OutboxEvent::query()
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('available_at')
                    ->orWhere('available_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($this->limit)
            ->get();

        if ($events->isEmpty()) {
            return;
        }

        $events->each(function (OutboxEvent $event): void {
            DB::transaction(function () use ($event): void {
                $event->refresh();
                if ($event->status !== 'pending') {
                    return;
                }

                $event->markProcessing();

                if ($event->event_type === \App\Domains\ExternalApi\Evaluation\Domain\Events\EvaluationCompleted::TYPE) {
                    $payload = $event->payload ?? [];
                    $sessionId = $payload['evaluation_session_id'] ?? null;
                    if (! $sessionId) {
                        $event->markFailed('Missing evaluation_session_id in outbox payload.');
                        return;
                    }

                    $existing = IntegrationJob::query()
                        ->where('provider', 'psm')
                        ->where('job_type', 'sync_evaluation')
                        ->where('evaluation_session_id', $sessionId)
                        ->first();

                    if (! $existing) {
                        $job = IntegrationJob::create([
                            'provider' => 'psm',
                            'job_type' => 'sync_evaluation',
                            'status' => 'pending',
                            'payload' => ['evaluation_session_id' => $sessionId],
                            'evaluation_session_id' => $sessionId,
                        ]);
                        SyncEvaluationToPSM::dispatch($job->id);
                    }

                    $event->markProcessed();
                    return;
                }

                $event->markFailed('Unsupported outbox event type.');
            });
        });
    }
}


