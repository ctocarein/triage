<?php

namespace Tests\Unit;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Services\Catalog\CatalogConsistencyChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogConsistencyCheckerTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_targets_are_accepted_when_questions_exist(): void
    {
        DynamicQuestionTemplate::create([
            'key' => 'entry_question',
            'text' => 'Entry question',
            'type' => 'text',
            'answer_target' => 'evaluation',
            'bloc_key' => 'bloc_test',
            'order' => 1,
            'is_active' => true,
            'routing_rules' => [
                ['if' => true, 'next' => 'queue:queued_question'],
            ],
        ]);

        DynamicQuestionTemplate::create([
            'key' => 'queued_question',
            'text' => 'Queued question',
            'type' => 'text',
            'answer_target' => 'evaluation',
            'bloc_key' => 'bloc_test',
            'order' => 2,
            'is_active' => true,
        ]);

        $errors = app(CatalogConsistencyChecker::class)->check();

        $this->assertSame([], $errors);
    }

    public function test_queue_targets_report_unknown_questions(): void
    {
        DynamicQuestionTemplate::create([
            'key' => 'entry_question',
            'text' => 'Entry question',
            'type' => 'text',
            'answer_target' => 'evaluation',
            'bloc_key' => 'bloc_test',
            'order' => 1,
            'is_active' => true,
            'routing_rules' => [
                ['if' => true, 'next' => 'queue:missing_question'],
            ],
        ]);

        $errors = app(CatalogConsistencyChecker::class)->check();

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('unknown queue question(s)', $errors[0]);
    }
}
