<?php

namespace Tests\Unit;

use App\Domains\Evaluation\Services\Navigation\ConditionEvaluator;
use Carbon\Carbon;
use Tests\TestCase;

class ConditionEvaluatorTest extends TestCase
{
    public function test_build_scope_computes_age_from_birth_date(): void
    {
        $evaluator = app(ConditionEvaluator::class);

        $scope = $evaluator->buildScope(
            answers: ['birth_date' => '2000-03-18'],
            profile: ['personal' => [], 'medical' => []],
        );

        $expectedAge = Carbon::parse('2000-03-18')->age;

        $this->assertSame($expectedAge, $scope['answers']['age']);
        $this->assertSame($expectedAge, $scope['profile']['personal']['age']);
    }

    public function test_question_age_condition_works_without_explicit_age_answer(): void
    {
        $evaluator = app(ConditionEvaluator::class);

        $scope = $evaluator->buildScope(
            answers: ['birth_date' => '2000-03-18'],
            profile: ['personal' => [], 'medical' => []],
        );

        $matches = $evaluator->evaluate([
            'source' => 'question',
            'key' => 'age',
            'operator' => '>=',
            'value' => 18,
        ], $scope);

        $this->assertTrue($matches);
    }
}
