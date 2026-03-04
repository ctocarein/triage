<?php

namespace App\Support;

trait HandlesConditionArrays
{
    protected function isList(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }

    protected function decodeJsonIfNeeded(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
