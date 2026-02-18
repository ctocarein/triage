<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\ModelVersion;
use App\Domains\Evaluation\Models\RiskModel;
use App\Domains\Evaluation\Models\RiskSignal;

class CheckRiskSignals extends Command
{
    protected $signature = 'vnext:check-risk-signals {--model=breast_v1} {--version=} {--csv=}';
    protected $description = 'Compare risk_weight (questions) vs risk_signals (vNext).';

    public function handle(): int
    {
        $modelKey = (string) $this->option('model');
        $versionOpt = $this->option('version');
        $csvPath = $this->option('csv');

        $riskModel = RiskModel::query()->where('key', $modelKey)->first();
        if (! $riskModel) {
            $this->error("Risk model '{$modelKey}' introuvable.");
            return self::FAILURE;
        }

        $modelVersion = $this->resolveModelVersion($versionOpt);
        if (! $modelVersion) {
            $this->error('Aucune version risk_model active trouvee.');
            return self::FAILURE;
        }

        $signals = RiskSignal::query()
            ->where('risk_model_id', $riskModel->id)
            ->where('model_version_id', $modelVersion->id)
            ->where('is_active', true)
            ->get()
            ->keyBy('fact_key');

        $questions = DynamicQuestionTemplate::query()
            ->where('risk_weight', '>', 0)
            ->get(['key', 'risk_weight']);

        $missingSignals = [];
        $weightMismatches = [];
        foreach ($questions as $question) {
            $signal = $signals->get($question->key);
            if (! $signal) {
                $missingSignals[] = $question->key;
                continue;
            }

            if ((int) $signal->weight !== (int) $question->risk_weight) {
                $weightMismatches[] = [
                    'key' => $question->key,
                    'risk_weight' => (int) $question->risk_weight,
                    'signal_weight' => (int) $signal->weight,
                ];
            }
        }

        $extraSignals = $signals->keys()->diff($questions->pluck('key'))->values()->all();

        $this->info("Model: {$riskModel->key} | Version: {$modelVersion->version}");
        $this->line('Questions (risk_weight > 0): ' . $questions->count());
        $this->line('Signals actifs: ' . $signals->count());

        if (! empty($missingSignals)) {
            $this->warn('Signals manquants pour questions:');
            foreach ($missingSignals as $key) {
                $this->line('- ' . $key);
            }
        }

        if (! empty($weightMismatches)) {
            $this->warn('Mismatches poids:');
            foreach ($weightMismatches as $row) {
                $this->line("- {$row['key']}: risk_weight={$row['risk_weight']} vs signal={$row['signal_weight']}");
            }
        }

        if (! empty($extraSignals)) {
            $this->warn('Signals sans question risk_weight:');
            foreach ($extraSignals as $key) {
                $this->line('- ' . $key);
            }
        }

        if ($csvPath) {
            $this->writeCsv((string) $csvPath, $questions, $signals, $missingSignals, $weightMismatches, $extraSignals);
            $this->info('CSV exporte vers: ' . $csvPath);
        }

        if (empty($missingSignals) && empty($weightMismatches) && empty($extraSignals)) {
            $this->info('OK: coherence complete.');
        }

        return self::SUCCESS;
    }

    protected function writeCsv(
        string $path,
        $questions,
        $signals,
        array $missingSignals,
        array $weightMismatches,
        array $extraSignals
    ): void {
        $rows = [];

        foreach ($questions as $question) {
            $signal = $signals->get($question->key);
            $status = 'ok';
            $signalWeight = $signal ? (int) $signal->weight : null;

            if (in_array($question->key, $missingSignals, true)) {
                $status = 'missing_signal';
            } elseif ($signal && (int) $signal->weight !== (int) $question->risk_weight) {
                $status = 'weight_mismatch';
            }

            $rows[] = [
                'key' => $question->key,
                'risk_weight' => (int) $question->risk_weight,
                'signal_weight' => $signalWeight,
                'status' => $status,
            ];
        }

        foreach ($extraSignals as $key) {
            $rows[] = [
                'key' => $key,
                'risk_weight' => null,
                'signal_weight' => (int) $signals->get($key)->weight,
                'status' => 'extra_signal',
            ];
        }

        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = new \SplFileObject($path, 'w');
        $file->fputcsv(['key', 'risk_weight', 'signal_weight', 'status']);
        foreach ($rows as $row) {
            $file->fputcsv([$row['key'], $row['risk_weight'], $row['signal_weight'], $row['status']]);
        }
    }

    protected function resolveModelVersion(?string $version): ?ModelVersion
    {
        if ($version) {
            return ModelVersion::query()
                ->where('name', 'risk_model')
                ->where('version', $version)
                ->first();
        }

        return ModelVersion::query()
            ->where('name', 'risk_model')
            ->where('is_active', true)
            ->orderByDesc('released_at')
            ->first();
    }
}
