<?php

namespace App\Support;

trait ComparesValues
{
    protected function compare($actual, $expected, string $operator): bool
    {
        if (is_string($actual)) {
            $actual = trim($actual);
        }

        switch ($operator) {
            case '=':
                return $actual == $expected;
            case '!=':
                return $actual != $expected;
            case '>':
                return $actual > $expected;
            case '<':
                return $actual < $expected;
            case '>=':
                return $actual >= $expected;
            case '<=':
                return $actual <= $expected;
            case 'in':
                return in_array($actual, (array) $expected);
            case 'not_in':
                return !in_array($actual, (array) $expected);
            // contains : l'actual (tableau multiselect) contient la valeur expected
            case 'contains':
                return in_array($expected, (array) $actual);
            case 'not_contains':
                return !in_array($expected, (array) $actual);
            case 'age_gte':
            case 'age_gt':
            case 'age_lte':
            case 'age_lt':
            case 'age_eq':
                if (!$actual) return false;
                try {
                    $date = \Carbon\Carbon::parse($actual);
                    $age = $date->age;
                } catch (\Exception $e) {
                    return false;
                }
                return match ($operator) {
                    'age_gte' => $age >= $expected,
                    'age_gt'  => $age >  $expected,
                    'age_lte' => $age <= $expected,
                    'age_lt'  => $age <  $expected,
                    'age_eq'  => $age == $expected,
                };
            case 'empty':
                return empty($actual);
            case 'not_empty':
                return !empty($actual);
            default:
                return false;
        }
    }
}
