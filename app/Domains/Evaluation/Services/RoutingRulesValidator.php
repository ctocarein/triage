<?php

namespace App\Domains\Evaluation\Services;

class RoutingRulesValidator
{
    private const ALLOWED_OPERATORS = [
        '=', '!=', '>', '<', '>=', '<=', 'in', 'not_in',
        'age_gte', 'age_gt', 'age_lte', 'age_lt', 'age_eq',
        'empty', 'not_empty',
    ];

    /**
     * @param mixed $rules
     * @param array<int, string> $knownQuestionKeys
     * @param array<int, string> $knownBlocKeys
     * @return array<int, string>
     */
    public function validateRoutingRules(
        mixed $rules,
        array $knownQuestionKeys = [],
        array $knownBlocKeys = [],
        string $questionKey = 'unknown'
    ): array {
        $errors = [];

        if (is_string($rules)) {
            $decoded = json_decode($rules, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ["routing_rules JSON invalide pour {$questionKey}: " . json_last_error_msg() . "."];
            }
            $rules = $decoded;
        }

        if (!is_array($rules)) {
            return ["routing_rules doit etre un tableau pour {$questionKey}."];
        }

        if (!$this->isList($rules)) {
            return ["routing_rules doit etre une liste pour {$questionKey}."];
        }

        foreach ($rules as $index => $rule) {
            if (!is_array($rule)) {
                $errors[] = "routing_rules[{$index}] doit etre un objet pour {$questionKey}.";
                continue;
            }

            $unknownKeys = array_diff(array_keys($rule), ['if', 'next']);
            if (!empty($unknownKeys)) {
                $errors[] = "routing_rules[{$index}] contient des cles interdites (" . implode(', ', $unknownKeys) . ") pour {$questionKey}.";
            }

            if (!array_key_exists('if', $rule)) {
                $errors[] = "routing_rules[{$index}].if est requis pour {$questionKey}.";
            } else {
                $errors = array_merge(
                    $errors,
                    $this->validateCondition($rule['if'], "routing_rules[{$index}].if", $questionKey)
                );
            }

            if (!array_key_exists('next', $rule)) {
                $errors[] = "routing_rules[{$index}].next est requis pour {$questionKey}.";
            } else {
                $errors = array_merge(
                    $errors,
                    $this->validateNext($rule['next'], $knownQuestionKeys, $knownBlocKeys, "routing_rules[{$index}].next", $questionKey)
                );
            }
        }

        return $errors;
    }

    /**
     * @return array<int, string>
     */
    private function validateCondition(mixed $condition, string $path, string $questionKey): array
    {
        if (is_bool($condition) || is_string($condition) || is_int($condition) || is_float($condition)) {
            return [];
        }

        if (!is_array($condition)) {
            return ["{$path} doit etre un booleen, un scalaire ou un objet pour {$questionKey}."];
        }

        if ($this->isList($condition)) {
            return [];
        }

        $allowedKeys = ['operator', 'value', 'all', 'any'];
        $unknownKeys = array_diff(array_keys($condition), $allowedKeys);
        if (!empty($unknownKeys)) {
            return ["{$path} contient des cles interdites (" . implode(', ', $unknownKeys) . ") pour {$questionKey}."];
        }

        if (isset($condition['all']) || isset($condition['any'])) {
            $errors = [];

            if (isset($condition['all'])) {
                if (!is_array($condition['all']) || !$this->isList($condition['all'])) {
                    $errors[] = "{$path}.all doit etre une liste pour {$questionKey}.";
                } else {
                    foreach ($condition['all'] as $index => $item) {
                        $errors = array_merge(
                            $errors,
                            $this->validateCondition($item, "{$path}.all[{$index}]", $questionKey)
                        );
                    }
                }
            }

            if (isset($condition['any'])) {
                if (!is_array($condition['any']) || !$this->isList($condition['any'])) {
                    $errors[] = "{$path}.any doit etre une liste pour {$questionKey}.";
                } else {
                    foreach ($condition['any'] as $index => $item) {
                        $errors = array_merge(
                            $errors,
                            $this->validateCondition($item, "{$path}.any[{$index}]", $questionKey)
                        );
                    }
                }
            }

            return $errors;
        }

        if (array_key_exists('operator', $condition) || array_key_exists('value', $condition)) {
            $operator = $condition['operator'] ?? '=';
            if (!is_string($operator) || !in_array($operator, self::ALLOWED_OPERATORS, true)) {
                return ["{$path}.operator invalide pour {$questionKey} (valeurs: " . implode(', ', self::ALLOWED_OPERATORS) . ")."];
            }
            return [];
        }

        return [];
    }

    /**
     * @param mixed $next
     * @return array<int, string>
     */
    private function validateNext(
        mixed $next,
        array $knownQuestionKeys,
        array $knownBlocKeys,
        string $path,
        string $questionKey
    ): array {
        if (!is_string($next) || $next === '') {
            return ["{$path} doit etre une string non vide pour {$questionKey}."];
        }

        if ($next === 'end_session') {
            return [];
        }

        if (str_starts_with($next, 'level:')) {
            return [];
        }

        if (str_starts_with($next, 'bloc:')) {
            $blocKey = substr($next, strlen('bloc:'));
            if ($blocKey === '') {
                return ["{$path} doit contenir une cle bloc apres 'bloc:' pour {$questionKey}."];
            }
            if (!empty($knownBlocKeys) && !in_array($blocKey, $knownBlocKeys, true)) {
                return ["{$path} reference un bloc inconnu ({$blocKey}) pour {$questionKey}."];
            }
            return [];
        }

        if (str_starts_with($next, 'question:')) {
            $qKey = substr($next, strlen('question:'));
            if ($qKey === '') {
                return ["{$path} doit contenir une cle question apres 'question:' pour {$questionKey}."];
            }
            if (!empty($knownQuestionKeys) && !in_array($qKey, $knownQuestionKeys, true)) {
                return ["{$path} reference une question inconnue ({$qKey}) pour {$questionKey}."];
            }
            return [];
        }

        return ["{$path} doit utiliser un prefixe explicite (question:, bloc:, level:) ou end_session pour {$questionKey}."];
    }

    private function isList(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }
}
