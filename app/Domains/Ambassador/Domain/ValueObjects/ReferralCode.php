<?php

namespace App\Domains\Ambassador\Domain\ValueObjects;

class ReferralCode
{
    public function __construct(public readonly string $value)
    {
    }
}
