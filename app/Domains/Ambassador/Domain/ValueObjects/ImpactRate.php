<?php

namespace App\Domains\Ambassador\Domain\ValueObjects;

class ImpactRate
{
    public function __construct(public readonly float $value)
    {
    }
}
