<?php

namespace App\Console\Commands;

use App\Domains\Evaluation\Services\GoldenPathRunner;
use Illuminate\Console\Command;

class ReplayGoldenPaths extends Command
{
    protected $signature = 'evaluation:golden-path {fixture? : Relative path to a fixture JSON file}';

    protected $description = 'Replay golden-path fixtures in dry-run mode (transaction rollback).';

    public function handle(GoldenPathRunner $runner): int
    {
        $fixtureArg = $this->argument('fixture');
        $baseDir = base_path('database/fixtures/golden-paths');

        $paths = [];
        if (is_string($fixtureArg) && $fixtureArg !== '') {
            $path = str_starts_with($fixtureArg, DIRECTORY_SEPARATOR)
                ? $fixtureArg
                : base_path($fixtureArg);
            if (!is_file($path)) {
                $this->error("Fixture file not found: {$path}");
                return self::FAILURE;
            }
            $paths[] = $path;
        } else {
            $paths = glob($baseDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
            if ($paths === []) {
                $this->warn("No fixtures found in {$baseDir}");
                return self::SUCCESS;
            }
        }

        $hasFailure = false;
        foreach ($paths as $path) {
            $raw = file_get_contents($path);
            $fixture = is_string($raw) ? json_decode($raw, true) : null;

            if (!is_array($fixture)) {
                $hasFailure = true;
                $this->error("Invalid JSON fixture: {$path}");
                continue;
            }

            $result = $runner->runFixture($fixture);
            if (!($result['ok'] ?? false)) {
                $hasFailure = true;
                $this->error("FAIL {$result['name']}: {$result['error']}");
                continue;
            }

            $this->info("PASS {$result['name']}");
            foreach ($result['steps'] as $step) {
                $routingAction = $step['routing']['action'] ?? 'none';
                $routingBloc = $step['routing']['bloc_key'] ?? '-';
                $next = $step['next_question_key'] ?? '-';
                $this->line(
                    "  step {$step['step']} {$step['question_key']} | routing={$routingAction} bloc={$routingBloc} | next={$next}"
                );
            }
        }

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }
}

