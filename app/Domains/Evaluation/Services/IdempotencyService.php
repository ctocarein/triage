<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Evaluation\Models\IdempotencyKey;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IdempotencyService
{
    public function requireKey(Request $request): string
    {
        $key = trim((string) $request->header('Idempotency-Key'));
        if ($key === '') {
            $key = (string) Str::uuid();
        }

        return $key;
    }

    public function hashRequest(Request $request): string
    {
        $payload = [
            'method' => $request->method(),
            'path' => $request->path(),
            'identity_id' => $request->user()?->getAuthIdentifier(),
            'body' => $request->all(),
        ];

        return hash('sha256', json_encode($payload));
    }

    /**
     * @return array{status:int,body:array<string,mixed>}|null
     */
    public function getResponse(string $key, string $scope, string $requestHash): ?array
    {
        $record = IdempotencyKey::query()
            ->where('key', $key)
            ->where('scope', $scope)
            ->first();

        if (! $record) {
            return null;
        }

        if ($record->expires_at && $record->expires_at->isPast()) {
            $record->delete();
            return null;
        }

        if ($record->request_hash && $record->request_hash !== $requestHash) {
            abort(409, 'Idempotency key reused with different payload.');
        }

        return $record->response ?: null;
    }

    /**
     * @param array<string,mixed> $body
     */
    public function store(string $key, string $scope, string $requestHash, array $body, int $status, int $ttlHours = 24): void
    {
        IdempotencyKey::updateOrCreate(
            ['key' => $key, 'scope' => $scope],
            [
                'request_hash' => $requestHash,
                'response' => [
                    'status' => $status,
                    'body' => $body,
                ],
                'expires_at' => now()->addHours($ttlHours),
            ]
        );
    }
}
