<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\ModelVersion;
use App\Domains\Evaluation\Models\RiskModel;
use App\Domains\Evaluation\Models\RiskFlag;

class RiskFlagsSeeder extends Seeder
{
    public function run(): void
    {
        $riskModel = RiskModel::query()->where('key', 'breast_v1')->first();
        $modelVersion = ModelVersion::query()
            ->where('name', 'risk_model')
            ->where('is_active', true)
            ->orderByDesc('released_at')
            ->first();

        if (! $riskModel || ! $modelVersion) {
            return;
        }

        $flags = [
            ['fact_key' => 'breast_nipple_bleeding', 'type' => 'urgent', 'priority' => 100],
            ['fact_key' => 'breast_skin_orange', 'type' => 'urgent', 'priority' => 100],
            ['fact_key' => 'pelvic_mass', 'type' => 'urgent', 'priority' => 100],
        ];

        foreach ($flags as $flag) {
            RiskFlag::updateOrCreate(
                [
                    'risk_model_id' => $riskModel->id,
                    'model_version_id' => $modelVersion->id,
                    'fact_key' => $flag['fact_key'],
                    'type' => $flag['type'],
                ],
                [
                    'priority' => $flag['priority'],
                    'is_active' => true,
                ]
            );
        }
    }
}
