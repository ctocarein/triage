<?php

namespace Tests\Unit;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckRoutingCyclesTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_detects_cycle(): void
    {
        DynamicQuestionTemplate::create([
            'key' => 'question_a',
            'text' => 'Question A',
            'type' => 'boolean',
            'bloc_key' => 'bloc_a',
            'routing_rules' => [
                ['if' => true, 'next' => 'question:question_b'],
            ],
            'is_entry_point' => true,
        ]);

        DynamicQuestionTemplate::create([
            'key' => 'question_b',
            'text' => 'Question B',
            'type' => 'boolean',
            'bloc_key' => 'bloc_b',
            'routing_rules' => [
                ['if' => true, 'next' => 'question:question_a'],
            ],
            'is_entry_point' => true,
        ]);

        $this->artisan('vnext:check-routing-cycles')
            ->expectsOutputToContain('Cycles detectes')
            ->assertExitCode(1);
    }
}
