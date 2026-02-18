<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\FactMapping;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;

class FactMappingsSeeder extends Seeder
{
    public function run(): void
    {
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
            ->get(['key', 'type']);

        foreach ($questions as $question) {
            $answerValue = null;
            $operator = null;

            if ($question->type === 'boolean') {
                $answerValue = true;
                $operator = '=';
            }

            FactMapping::updateOrCreate(
                [
                    'question_key' => $question->key,
                    'fact_key' => $question->key,
                ],
                [
                    'answer_value' => $answerValue,
                    'operator' => $operator,
                    'is_active' => true,
                ]
            );
        }
    }
}
