<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluation_seeders_run_without_errors(): void
    {
        $this->seed();

        $this->assertDatabaseHas('dynamic_question_templates', [
            'key' => 'consent_declined_thank_you',
        ]);
    }
}
