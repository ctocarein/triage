<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Decision\Models\DecisionPath;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RuntimeException;

class GoldenPathRunner
{
    public function __construct(
        private readonly AnswerService $answerService,
        private readonly QuestionFlowService $flow
    ) {
    }

    /**
     * @param array<string, mixed> $fixture
     * @return array<string, mixed>
     */
    public function runFixture(array $fixture): array
    {
        return Event::fakeFor(function () use ($fixture): array {
            $name = (string) ($fixture['name'] ?? 'unnamed');
            $steps = $fixture['steps'] ?? [];
            if (!is_array($steps)) {
                throw new RuntimeException("Fixture '{$name}' has invalid steps.");
            }

            DB::beginTransaction();

            try {
                $identity = Identity::create([
                    'id' => (string) Str::uuid(),
                    'kind' => 'person',
                    'status' => 'active',
                ]);
                $path = DecisionPath::query()->where('is_active', true)->first();
                if (!$path) {
                    throw new RuntimeException("Fixture '{$name}': no active decision path found.");
                }

                $session = EvaluationSession::create([
                    'identity_id' => $identity->id,
                    'decision_path_id' => $path->id,
                    'status' => 'in_progress',
                    'type' => $fixture['type'] ?? 'complete',
                    'started_at' => now(),
                    'visited_blocs' => [],
                    'context' => [],
                ]);

                $results = [];
                foreach ($steps as $idx => $step) {
                    if (!is_array($step)) {
                        throw new RuntimeException("Fixture '{$name}', step {$idx} must be an object.");
                    }

                    $questionKey = $step['question_key'] ?? null;
                    if (!is_string($questionKey) || $questionKey === '') {
                        throw new RuntimeException("Fixture '{$name}', step {$idx} requires question_key.");
                    }

                    $question = DynamicQuestionTemplate::query()->where('key', $questionKey)->first();
                    if (!$question) {
                        throw new RuntimeException("Fixture '{$name}', step {$idx} unknown question '{$questionKey}'.");
                    }

                    $value = $step['value'] ?? null;
                    $answer = $this->answerService->saveAnswer([
                        'evaluation_session_id' => $session->id,
                        'dynamic_question_template_id' => $question->id,
                        'value' => $value,
                        'is_skipped' => false,
                    ]);

                    $routing = $this->flow->applyRoutingRules($session, $question, $answer->value);
                    $next = null;
                    if (($routing['action'] ?? null) !== 'end_session') {
                        $next = $this->flow->goToNextQuestion($session);
                    }

                    $results[] = [
                        'step' => $idx + 1,
                        'question_key' => $questionKey,
                        'routing' => $routing,
                        'next_question_key' => $next?->key,
                    ];

                    $expect = $step['expect'] ?? null;
                    if (is_array($expect)) {
                        $this->assertExpectation($name, $idx, $expect, $routing, $next?->key);
                    }
                }

                DB::rollBack();

                return [
                    'name' => $name,
                    'ok' => true,
                    'steps' => $results,
                ];
            } catch (\Throwable $e) {
                DB::rollBack();

                return [
                    'name' => $name,
                    'ok' => false,
                    'error' => $e->getMessage(),
                ];
            }
        });
    }

    /**
     * @param array<string, mixed> $expect
     * @param array<string, mixed>|null $routing
     */
    private function assertExpectation(
        string $fixtureName,
        int $index,
        array $expect,
        ?array $routing,
        ?string $nextQuestionKey
    ): void {
        if (isset($expect['routing']) && is_array($expect['routing'])) {
            foreach ($expect['routing'] as $field => $expectedValue) {
                $actual = $routing[$field] ?? null;
                if ($actual !== $expectedValue) {
                    throw new RuntimeException(
                        "Fixture '{$fixtureName}', step " . ($index + 1) .
                        " expected routing.{$field}='{$expectedValue}', got '" . var_export($actual, true) . "'."
                    );
                }
            }
        }

        if (array_key_exists('next_question_key', $expect)) {
            $expectedNext = $expect['next_question_key'];
            if ($nextQuestionKey !== $expectedNext) {
                throw new RuntimeException(
                    "Fixture '{$fixtureName}', step " . ($index + 1) .
                    " expected next_question_key='{$expectedNext}', got '" . var_export($nextQuestionKey, true) . "'."
                );
            }
        }
    }
}
