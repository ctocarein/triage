<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Support\HandlesConditionArrays;

class CatalogConsistencyChecker
{
    use HandlesConditionArrays;

    /**
     * @return array<int, string>
     */
    public function check(): array
    {
        $templates = DynamicQuestionTemplate::query()
            ->get(['key', 'bloc_key', 'order', 'visible_if', 'routing_rules']);

        $questionKeys = $templates->pluck('key')->filter()->values()->all();
        $blocKeys = $templates->pluck('bloc_key')->filter()->unique()->values()->all();

        $errors = [];

        // Duplicate order in same bloc (DB state)
        foreach ($templates->groupBy('bloc_key') as $blocKey => $group) {
            if (!$blocKey) {
                continue;
            }

            $byOrder = $group->groupBy('order')->filter(fn ($items, $order) => $order !== null && $items->count() > 1);
            foreach ($byOrder as $order => $items) {
                $keys = $items->pluck('key')->implode(', ');
                $errors[] = "Duplicate order '{$order}' in bloc '{$blocKey}' for: {$keys}.";
            }
        }

        foreach ($templates as $template) {
            $errors = array_merge(
                $errors,
                $this->checkVisibleIfRefs($template->key, $template->visible_if, $questionKeys)
            );

            $errors = array_merge(
                $errors,
                $this->checkRoutingTargets($template->key, $template->routing_rules, $questionKeys, $blocKeys)
            );
        }

        return $errors;
    }

    /**
     * @param array<int, string> $questionKeys
     * @return array<int, string>
     */
    private function checkVisibleIfRefs(string $questionKey, mixed $visibleIf, array $questionKeys): array
    {
        $errors = [];
        $condition = $this->decodeJsonIfNeeded($visibleIf);
        if ($condition === null) {
            return [];
        }

        foreach ($this->extractQuestionRefs($condition) as $ref) {
            if (!in_array($ref, $questionKeys, true)) {
                $errors[] = "visible_if of '{$questionKey}' references unknown question key '{$ref}'.";
            }
        }

        return $errors;
    }

    /**
     * @param array<int, string> $questionKeys
     * @param array<int, string> $blocKeys
     * @return array<int, string>
     */
    private function checkRoutingTargets(
        string $questionKey,
        mixed $rules,
        array $questionKeys,
        array $blocKeys
    ): array {
        $errors = [];
        $decoded = $this->decodeJsonIfNeeded($rules);
        if (!is_array($decoded)) {
            return [];
        }

        foreach ($decoded as $index => $rule) {
            if (!is_array($rule) || !isset($rule['next']) || !is_string($rule['next'])) {
                continue;
            }

            $next = $rule['next'];
            if ($next === 'end_session' || str_starts_with($next, 'end_session:') || str_starts_with($next, 'level:')) {
                continue;
            }

            if (str_starts_with($next, 'bloc:')) {
                $bloc = substr($next, strlen('bloc:'));
                if ($bloc === '' || !in_array($bloc, $blocKeys, true)) {
                    $errors[] = "routing_rules[{$index}] of '{$questionKey}' targets unknown bloc '{$next}'.";
                }
                continue;
            }

            if (str_starts_with($next, 'question:')) {
                $targetKey = substr($next, strlen('question:'));
                if ($targetKey === '' || !in_array($targetKey, $questionKeys, true)) {
                    $errors[] = "routing_rules[{$index}] of '{$questionKey}' targets unknown question '{$next}'.";
                }
                continue;
            }

            $errors[] = "routing_rules[{$index}] of '{$questionKey}' has unsupported next '{$next}'.";
        }

        return $errors;
    }

    /**
     * @return array<int, string>
     */
    private function extractQuestionRefs(mixed $condition): array
    {
        $refs = [];
        $this->collectQuestionRefs($condition, $refs);
        return array_values(array_unique($refs));
    }

    /**
     * @param array<int, string> $refs
     */
    private function collectQuestionRefs(mixed $condition, array &$refs): void
    {
        if (!is_array($condition)) {
            return;
        }

        if ($this->isList($condition)) {
            foreach ($condition as $item) {
                $this->collectQuestionRefs($item, $refs);
            }
            return;
        }

        if (($condition['source'] ?? 'question') === 'question') {
            $key = $condition['key'] ?? $condition['question'] ?? null;
            if (is_string($key) && $key !== '') {
                $refs[] = $key;
            }
        }

        foreach (['all', 'any'] as $groupKey) {
            if (isset($condition[$groupKey]) && is_array($condition[$groupKey])) {
                foreach ($condition[$groupKey] as $item) {
                    $this->collectQuestionRefs($item, $refs);
                }
            }
        }

        if (array_key_exists('not', $condition)) {
            $this->collectQuestionRefs($condition['not'], $refs);
        }
    }

}
