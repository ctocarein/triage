<?php

namespace Database\Factories;

use App\Domains\Evaluation\Models\ThematicBloc;
use Illuminate\Database\Eloquent\Factories\Factory;

class ThematicBlocFactory extends Factory
{
    protected $model = ThematicBloc::class;

    public function definition(): array
    {
        return [
            'key'       => 'bloc_' . $this->faker->unique()->lexify('????'),
            'label'     => $this->faker->words(2, true),
            'order'     => $this->faker->numberBetween(1, 100),
            'is_active' => true,
            'color'     => $this->faker->hexColor(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
