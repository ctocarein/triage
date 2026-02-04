<?php

namespace App\Domains\ExternalApi\Integration\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationJob extends Model
{
    protected $fillable = [
        'provider',
        'job_type',
        'status',
        'attempts',
        'last_attempt_at',
        'next_run_at',
        'last_error',
        'payload',
        'response',
        'evaluation_session_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
        'last_attempt_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function markProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'attempts' => $this->attempts + 1,
            'last_attempt_at' => now(),
        ]);
    }

    public function markSuccess(array $response = []): void
    {
        $this->update([
            'status' => 'success',
            'response' => $response,
            'last_error' => null,
        ]);
    }

    public function markFailed(string $message, array $response = []): void
    {
        $this->update([
            'status' => 'failed',
            'last_error' => $message,
            'response' => $response ?: $this->response,
        ]);
    }

    public function markSkipped(string $message): void
    {
        $this->update([
            'status' => 'skipped',
            'last_error' => $message,
        ]);
    }
}


