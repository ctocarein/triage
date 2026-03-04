<?php

namespace Database\Factories;

use App\Domains\Evaluation\Models\RiskModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class RiskModelFactory extends Factory
{
    protected $model = RiskModel::class;

    public function definition(): array
    {
        return [
            'key'       => 'risk_' . $this->faker->unique()->lexify('????'),
            'name'      => $this->faker->words(3, true),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
