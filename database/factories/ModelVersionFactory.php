<?php

namespace Database\Factories;

use App\Domains\Evaluation\Models\ModelVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class ModelVersionFactory extends Factory
{
    protected $model = ModelVersion::class;

    public function definition(): array
    {
        return [
            'name'        => $this->faker->randomElement(['catalog', 'risk_model']),
            'version'     => $this->faker->semver(),
            'description' => $this->faker->sentence(),
            'is_active'   => false,
            'released_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function forCatalog(): static
    {
        return $this->state(['name' => 'catalog']);
    }

    public function forRiskModel(): static
    {
        return $this->state(['name' => 'risk_model']);
    }
}
