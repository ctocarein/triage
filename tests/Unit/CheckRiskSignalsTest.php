<?php

namespace Tests\Unit;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\Fact;
use App\Domains\Evaluation\Models\ModelVersion;
use App\Domains\Evaluation\Models\RiskModel;
use App\Domains\Evaluation\Models\RiskSignal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckRiskSignalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_reports_coherent_signals_and_can_export_csv(): void
    {
        ModelVersion::create([
            'name' => 'risk_model',
            'version' => '1.0',
            'description' => 'test',
            'is_active' => true,
        ]);

        $riskModel = RiskModel::create([
            'key' => 'breast_v1',
            'name' => 'Breast v1',
            'is_active' => true,
        ]);

        $question = DynamicQuestionTemplate::create([
            'key' => 'breast_mass',
            'text' => 'Test question',
            'type' => 'boolean',
            'risk_weight' => 5,
        ]);

        $version = ModelVersion::query()->where('name', 'risk_model')->first();

        // risk_signals.fact_key est une FK vers facts.key : le fait doit exister
        // avant le signal, même s'il porte la même clé que la question.
        Fact::create([
            'key'       => $question->key,
            'label'     => 'Masse mammaire',
            'is_active' => true,
        ]);

        RiskSignal::create([
            'risk_model_id' => $riskModel->id,
            'model_version_id' => $version->id,
            'fact_key' => $question->key,
            'weight' => 5,
            'is_active' => true,
        ]);

        $csvPath = storage_path('app/test-risk-signals.csv');

        $this->artisan('vnext:check-risk-signals', ['--csv' => $csvPath])
            ->expectsOutputToContain('OK: coherence complete.')
            ->assertExitCode(0);

        $this->assertFileExists($csvPath);
    }
}
