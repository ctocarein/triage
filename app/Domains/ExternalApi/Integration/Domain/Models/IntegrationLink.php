<?php

namespace App\Domains\ExternalApi\Integration\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class IntegrationLink extends Model
{
    protected $fillable = [
        'provider',
        'local_type',
        'local_id',
        'external_id',
        'psm_pin_encrypted',
        'external_payload',
        'meta',
    ];

    protected $casts = [
        'external_payload' => 'array',
        'meta' => 'array',
    ];

    public function setPsmPin(string $pin): void
    {
        $this->psm_pin_encrypted = Crypt::encryptString($pin);
    }

    public function getPsmPin(): ?string
    {
        if (! $this->psm_pin_encrypted) {
            return null;
        }

        return Crypt::decryptString($this->psm_pin_encrypted);
    }
}


