<?php

namespace App\Shared\Outbox\Services;

use App\Shared\Outbox\Models\OutboxEvent;

class OutboxRecorder
{
    public function record(string $type, string $aggregateId, array $payload, string $version = 'v1'): OutboxEvent
    {
        return OutboxEvent::create([
            'type' => $type,
            'version' => $version,
            'aggregate_id' => $aggregateId,
            'payload' => $payload,
            'status' => 'PENDING',
        ]);
    }
}
