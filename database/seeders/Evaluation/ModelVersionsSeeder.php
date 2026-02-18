<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\ModelVersion;

class ModelVersionsSeeder extends Seeder
{
    public function run(): void
    {
        ModelVersion::updateOrCreate(
            ['name' => 'risk_model', 'version' => '1.0'],
            [
                'description' => 'Version initiale du modele de risque vNext.',
                'checksum' => null,
                'metadata' => ['source' => 'vnext'],
                'is_active' => true,
                'released_at' => now(),
            ]
        );
    }
}