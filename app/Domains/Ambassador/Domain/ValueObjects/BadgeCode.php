<?php

namespace App\Domains\Ambassador\Domain\ValueObjects;

class BadgeCode
{
    public function __construct(public readonly string $value)
    {
    }
}
