<?php

namespace App\Domains\ExternalApi\Integration\Infrastructure\Repositories;

use App\Domains\ExternalApi\Integration\Domain\Models\IntegrationConfig;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class IntegrationConfigRepository
{
    public function get(string $provider, string $key): ?string
    {
        $config = IntegrationConfig::query()
            ->where('provider', $provider)
            ->where('config_key', $key)
            ->first();

        if (! $config) {
            return null;
        }

        if ($config->is_secret && $config->config_value !== null) {
            try {
                return Crypt::decryptString($config->config_value);
            } catch (\Throwable $e) {
                Log::warning('Integration config decryption failed.', [
                    'provider' => $provider,
                    'key' => $key,
                ]);
                return null;
            }
        }

        return $config->config_value;
    }
}


