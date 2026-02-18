<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;

class CheckRoutingCycles extends Command
{
    protected $signature = 'vnext:check-routing-cycles {--max=20}';
    protected $description = 'Detect cycles in routing_rules graph (questions/blocs).';

    public function handle(): int
    {
        $questions = DynamicQuestionTemplate::query()
            ->get(['key', 'bloc_key', 'routing_rules', 'is_entry_point']);

        $questionKeys = $questions->pluck('key')->all();
        $blocKeys = $questions->pluck('bloc_key')->filter()->unique()->values()->all();

        $entryByBloc = $questions
            ->filter(fn($q) => $q->bloc_key)
            ->groupBy('bloc_key')
            ->map(function ($items) {
                $entries = $items->filter(fn($q) => (bool) $q->is_entry_point)->pluck('key')->values()->all();
                return $entries ?: $items->pluck('key')->values()->all();
            })
            ->toArray();

        $graph = [];
        foreach ($questions as $question) {
            $from = $this->nodeQuestion($question->key);
            foreach ($this->extractTargets($question->routing_rules, $questionKeys, $blocKeys) as $target) {
                $graph[$from][] = $target;
            }
        }

        foreach ($entryByBloc as $blocKey => $entries) {
            $blocNode = $this->nodeBloc($blocKey);
            foreach ($entries as $qKey) {
                $graph[$blocNode][] = $this->nodeQuestion($qKey);
            }
        }

        $cycles = $this->findCycles($graph, (int) $this->option('max'));

        if (empty($cycles)) {
            $this->info('OK: aucun cycle detecte.');
            return self::SUCCESS;
        }

        $this->warn('Cycles detectes:');
        foreach ($cycles as $cycle) {
            $this->line('- ' . implode(' -> ', $cycle));
        }

        return self::FAILURE;
    }

    protected function extractTargets($rules, array $questionKeys, array $blocKeys): array
    {
        if (is_string($rules)) {
            $rules = json_decode($rules, true);
        }

        if (!is_array($rules)) {
            return [];
        }

        $targets = [];
        foreach ($rules as $rule) {
            if (!is_array($rule) || empty($rule['next'])) {
                continue;
            }

            $next = (string) $rule['next'];
            if ($next === 'end_session' || str_starts_with($next, 'end_session:') || str_starts_with($next, 'level:')) {
                continue;
            }

            if (str_starts_with($next, 'bloc:')) {
                $targets[] = $this->nodeBloc(substr($next, strlen('bloc:')));
                continue;
            }

            if (str_starts_with($next, 'question:')) {
                $targets[] = $this->nodeQuestion(substr($next, strlen('question:')));
                continue;
            }

            if (in_array($next, $blocKeys, true)) {
                $targets[] = $this->nodeBloc($next);
                continue;
            }

            if (in_array($next, $questionKeys, true)) {
                $targets[] = $this->nodeQuestion($next);
                continue;
            }
        }

        return $targets;
    }

    protected function nodeQuestion(string $key): string
    {
        return 'q:' . $key;
    }

    protected function nodeBloc(string $key): string
    {
        return 'b:' . $key;
    }

    protected function findCycles(array $graph, int $max): array
    {
        $cycles = [];
        $visited = [];
        $stack = [];
        $inStack = [];

        $visit = function ($node) use (&$visit, &$graph, &$visited, &$stack, &$inStack, &$cycles, $max) {
            if (count($cycles) >= $max) {
                return;
            }
            $visited[$node] = true;
            $inStack[$node] = true;
            $stack[] = $node;

            foreach ($graph[$node] ?? [] as $neighbor) {
                if (!isset($visited[$neighbor])) {
                    $visit($neighbor);
                } elseif (!empty($inStack[$neighbor])) {
                    $cycle = $this->extractCycle($stack, $neighbor);
                    if ($cycle) {
                        $cycles[] = $cycle;
                        if (count($cycles) >= $max) {
                            return;
                        }
                    }
                }
            }

            array_pop($stack);
            $inStack[$node] = false;
        };

        foreach (array_keys($graph) as $node) {
            if (!isset($visited[$node])) {
                $visit($node);
            }
        }

        return $cycles;
    }

    protected function extractCycle(array $stack, string $start): ?array
    {
        $index = array_search($start, $stack, true);
        if ($index === false) {
            return null;
        }

        $cycle = array_slice($stack, $index);
        $cycle[] = $start;

        return $cycle;
    }
}