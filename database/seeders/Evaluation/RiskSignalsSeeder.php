<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\ModelVersion;
use App\Domains\Evaluation\Models\RiskModel;
use App\Domains\Evaluation\Models\RiskSignal;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;

class RiskSignalsSeeder extends Seeder
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

        $keys = [
            'breast_pain',
            'breast_mass',
            'breast_mass_change',
            'breast_nipple_discharge',
            'breast_nipple_bleeding',
            'breast_nipple_retraction',
            'breast_nipple_eczema',
            'breast_skin_orange',
            'breast_skin_change',
            'breast_ulceration',
            'breast_asymmetry',
            'axillary_ganglion',
            'vaginal_bleeding_abnormal',
            'bleeding_amount',
            'bleeding_pain',
            'pelvic_pain',
            'vaginal_discharge',
            'pelvic_mass',
            'associated_troubles',
            'general_fatigue',
            'general_weightloss',
        ];

        $questions = DynamicQuestionTemplate::query()
            ->whereIn('key', $keys)
            ->get(['key', 'risk_weight']);

        foreach ($questions as $question) {
            $weight = (int) ($question->risk_weight ?? 0);
            if ($weight <= 0) {
                continue;
            }

            RiskSignal::updateOrCreate(
                [
                    'risk_model_id' => $riskModel->id,
                    'model_version_id' => $modelVersion->id,
                    'fact_key' => $question->key,
                ],
                [
                    'weight' => $weight,
                    'is_active' => true,
                ]
            );
        }
    }
}
