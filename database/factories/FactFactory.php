<?php

namespace Database\Factories;

use App\Domains\Evaluation\Models\Fact;
use Illuminate\Database\Eloquent\Factories\Factory;

class FactFactory extends Factory
{
    protected $model = Fact::class;

    public function definition(): array
    {
        return [
            'key'       => 'fact_' . $this->faker->unique()->lexify('????'),
            'label'     => $this->faker->words(3, true),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
