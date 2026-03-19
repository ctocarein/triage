<?php

namespace Tests\Unit;

use App\Domains\Decision\Models\DecisionPath;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Services\Navigation\RoutingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoutingEngineTest extends TestCase
{
    use RefreshDatabase;

    private function makeIdentity(): string
    {
        $id = (string) Str::uuid();

        DB::table('identities')->insert([
            'id' => $id,
            'kind' => 'person',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function makeSession(array $context = []): EvaluationSession
    {
        $path = DecisionPath::create([
            'key' => 'dp_routing_engine_test_' . uniqid(),
            'title' => 'Routing test path',
        ]);

        return EvaluationSession::create([
            'identity_id' => $this->makeIdentity(),
            'decision_path_id' => $path->id,
            'status' => 'in_progress',
            'type' => 'complete',
            'score' => 0,
            'context' => $context,
            'started_at' => now(),
        ]);
    }

    private function makeQuestion(string $key, int $order): DynamicQuestionTemplate
    {
        return DynamicQuestionTemplate::create([
            'key' => $key,
            'text' => $key,
            'type' => 'text',
            'bloc_key' => 'test_bloc',
            'answer_target' => 'evaluation',
            'order' => $order,
            'is_active' => true,
        ]);
    }

    public function test_forced_question_skips_answered_key_and_falls_back_to_queue(): void
    {
        $forced = $this->makeQuestion('forced_question', 1);
        $queued = $this->makeQuestion('queued_question', 2);

        $session = $this->makeSession([
            'forced_question_key' => $forced->key,
            'question_queue' => [$queued->key],
        ]);

        $question = app(RoutingEngine::class)->consumeForcedQuestion(
            $session,
            ['forced_question' => 'already answered'],
            ['personal' => [], 'medical' => []],
        );

        $this->assertNotNull($question);
        $this->assertSame('queued_question', $question->key);
    }

    public function test_question_queue_skips_answered_questions_using_keys(): void
    {
        $first = $this->makeQuestion('first_queued_question', 1);
        $second = $this->makeQuestion('second_queued_question', 2);

        $session = $this->makeSession([
            'question_queue' => [$first->key, $second->key],
        ]);

        $question = app(RoutingEngine::class)->consumeForcedQuestion(
            $session,
            ['first_queued_question' => 'already answered'],
            ['personal' => [], 'medical' => []],
        );

        $this->assertNotNull($question);
        $this->assertSame('second_queued_question', $question->key);
    }
}
