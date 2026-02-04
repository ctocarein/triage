<?php

namespace App\Domains\ExternalApi\Integration\Infrastructure\Connectors;

use App\Domains\ExternalApi\Integration\Infrastructure\Repositories\IntegrationConfigRepository;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PSMConnector
{
    public function __construct(protected IntegrationConfigRepository $config)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createPatient(array $payload): Response
    {
        $baseUrl = rtrim($this->resolveConfig('base_url'), '/');
        $token = $this->resolveConfig('token');
        $headerType = $this->resolveConfig('header_type');
        $headerValue = $this->resolveConfig('header_value');
        $timeout = (int) ($this->resolveConfig('timeout') ?? 10);

        return Http::withToken($token)
            ->withHeaders([
                'x-psm-type' => $headerType,
                'x-psm-value' => $headerValue,
            ])
            ->timeout($timeout)
            ->post($baseUrl . '/patients/create', $payload);
    }

    private function resolveConfig(string $key): ?string
    {
        $dbValue = $this->config->get('psm', $key);
        if ($dbValue !== null) {
            return $dbValue;
        }

        return config('services.psm.' . $key);
    }
}


