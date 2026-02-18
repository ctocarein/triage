<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\RiskModel;

class RiskModelsSeeder extends Seeder
{
    public function run(): void
    {
        RiskModel::updateOrCreate(
            ['key' => 'breast_v1'],
            [
                'name' => 'Breast v1',
                'description' => 'Modele de risque initial (breast).',
                'is_active' => true,
            ]
        );
    }
}