<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\Fact;

class FactsSeeder extends Seeder
{
    public function run(): void
    {
        $facts = [
            ['key' => 'breast_pain', 'label' => 'Douleur mammaire'],
            ['key' => 'breast_mass', 'label' => 'Masse mammaire'],
            ['key' => 'breast_mass_change', 'label' => 'Variation rapide de la masse'],
            ['key' => 'breast_nipple_discharge', 'label' => 'Ecoulement mamelonnaire'],
            ['key' => 'breast_nipple_bleeding', 'label' => 'Ecoulement sanglant'],
            ['key' => 'breast_nipple_retraction', 'label' => 'Retraction du mamelon'],
            ['key' => 'breast_nipple_eczema', 'label' => 'Eczema mamelonnaire'],
            ['key' => 'breast_skin_orange', 'label' => 'Peau d orange'],
            ['key' => 'breast_skin_change', 'label' => 'Changement cutane'],
            ['key' => 'breast_ulceration', 'label' => 'Ulceration'],
            ['key' => 'breast_asymmetry', 'label' => 'Asymetrie'],
            ['key' => 'axillary_ganglion', 'label' => 'Ganglion axillaire'],
            ['key' => 'vaginal_bleeding_abnormal', 'label' => 'Saignements anormaux'],
            ['key' => 'bleeding_amount', 'label' => 'Quantite de saignement'],
            ['key' => 'bleeding_pain', 'label' => 'Douleur associee aux saignements'],
            ['key' => 'pelvic_pain', 'label' => 'Douleur pelvienne'],
            ['key' => 'vaginal_discharge', 'label' => 'Pertes vaginales'],
            ['key' => 'pelvic_mass', 'label' => 'Masse pelvienne'],
            ['key' => 'associated_troubles', 'label' => 'Troubles associes'],
            ['key' => 'general_fatigue', 'label' => 'Fatigue generale'],
            ['key' => 'general_weightloss', 'label' => 'Perte de poids'],
        ];

        foreach ($facts as $fact) {
            Fact::updateOrCreate(
                ['key' => $fact['key']],
                [
                    'label' => $fact['label'],
                    'description' => $fact['description'] ?? null,
                    'is_active' => true,
                ]
            );
        }
    }
}
