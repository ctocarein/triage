<?php

namespace App\Domains\Evaluation\Services;

use App\Support\HandlesConditionArrays;

class RoutingRulesValidator
{
    use HandlesConditionArrays;

    private ConditionContractValidator $conditionValidator;

    public function __construct(?ConditionContractValidator $conditionValidator = null)
    {
        $this->conditionValidator = $conditionValidator ?? new ConditionContractValidator();
    }

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
                $condErrors = $this->conditionValidator->validate(
                    $rule['if'],
                    "routing_rules[{$index}].if"
                );
                foreach ($condErrors as $err) {
                    $errors[] = $err . " (question: {$questionKey})";
                }
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

        if ($next === 'end_session' || str_starts_with($next, 'end_session:')) {
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
}
