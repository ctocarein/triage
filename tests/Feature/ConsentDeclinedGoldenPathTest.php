<?php

namespace Tests\Feature;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Services\Insight\GoldenPathRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentDeclinedGoldenPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_consent_declined_flow_matches_golden_path_fixture(): void
    {
        $this->seed();

        $fixture = json_decode(
            file_get_contents(base_path('database/fixtures/golden-paths/greeting-consent-no.json')),
            true
        );

        $this->assertIsArray($fixture);

        $result = app(GoldenPathRunner::class)->runFixture($fixture);

        $this->assertTrue($result['ok'] ?? false, $result['error'] ?? 'Golden path failed.');
    }

    public function test_consent_declined_flow_allows_a_16_year_old_beneficiary(): void
    {
        $this->seed();

        $birthYear = (string) now()->subYears(16)->year;
        $yearQuestion = DynamicQuestionTemplate::where('key', 'consent_declined_year')->firstOrFail();
        $options = is_array($yearQuestion->options)
            ? $yearQuestion->options
            : json_decode((string) $yearQuestion->options, true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue(
            collect($options)->contains(fn (array $option): bool => ($option['value'] ?? null) === $birthYear),
            "The consent_declined_year question does not offer the year {$birthYear} for a 16-year-old beneficiary."
        );

        $fixture = [
            'name' => 'greeting-consent-no-16yo',
            'type' => 'complete',
            'steps' => [
                [
                    'question_key' => 'explicit_consent',
                    'value' => ['value' => 'no', 'label' => 'Non'],
                    'expect' => [
                        'routing' => [
                            'action' => 'jump_question',
                            'question_key' => 'consent_declined_thank_you',
                        ],
                        'next_question_key' => 'consent_declined_thank_you',
                    ],
                ],
                [
                    'question_key' => 'consent_declined_thank_you',
                    'value' => ['value' => 'continue', 'label' => 'Continuer'],
                    'expect' => [
                        'routing' => [
                            'action' => 'jump_question',
                            'question_key' => 'consent_declined_year',
                        ],
                        'next_question_key' => 'consent_declined_year',
                    ],
                ],
                [
                    'question_key' => 'consent_declined_year',
                    'value' => ['value' => $birthYear, 'label' => $birthYear],
                    'expect' => [
                        'routing' => [
                            'action' => 'jump_question',
                            'question_key' => 'consent_declined_commune',
                        ],
                        'next_question_key' => 'consent_declined_commune',
                    ],
                ],
                [
                    'question_key' => 'consent_declined_commune',
                    'value' => ['value' => 'Abobo', 'label' => 'Abobo'],
                    'expect' => [
                        'routing' => [
                            'action' => 'jump_question',
                            'question_key' => 'consent_declined_whatsapp',
                        ],
                        'next_question_key' => 'consent_declined_whatsapp',
                    ],
                ],
                [
                    'question_key' => 'consent_declined_whatsapp',
                    'value' => '0700000000',
                    'expect' => [
                        'routing' => [
                            'action' => 'jump_question',
                            'question_key' => 'consent_declined_end',
                        ],
                        'next_question_key' => 'consent_declined_end',
                    ],
                ],
                [
                    'question_key' => 'consent_declined_end',
                    'value' => ['value' => 'done', 'label' => 'Terminer'],
                    'expect' => [
                        'routing' => [
                            'action' => 'end_session',
                            'status' => 'cancelled',
                            'reason' => 'consent_declined',
                        ],
                        'next_question_key' => null,
                    ],
                ],
            ],
        ];

        $result = app(GoldenPathRunner::class)->runFixture($fixture);

        $this->assertTrue($result['ok'] ?? false, $result['error'] ?? 'Golden path failed for 16-year-old beneficiary.');
    }
}
