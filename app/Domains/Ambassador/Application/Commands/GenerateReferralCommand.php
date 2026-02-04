<?php

namespace App\Domains\Ambassador\Application\Commands;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class GenerateReferralCommand
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly Identity $actor,
        public readonly array $payload,
    ) {
    }
}
