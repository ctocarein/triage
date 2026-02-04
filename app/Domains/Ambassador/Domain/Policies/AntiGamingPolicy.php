<?php

namespace App\Domains\Ambassador\Domain\Policies;

class AntiGamingPolicy
{
    public function isValidUsage(?string $source): bool
    {
        if ($source === null) {
            return true;
        }

        return strlen($source) <= 50;
    }
}
