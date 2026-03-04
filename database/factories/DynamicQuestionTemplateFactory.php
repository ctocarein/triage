<?php

namespace Database\Factories;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class DynamicQuestionTemplateFactory extends Factory
{
    protected $model = DynamicQuestionTemplate::class;

    public function definition(): array
    {
        return [
            'key'         => 'q_' . $this->faker->unique()->lexify('????'),
            'text'        => $this->faker->sentence(),
            'type'        => $this->faker->randomElement(['boolean', 'text', 'select_one', 'number']),
            'answer_target'=> 'evaluation',
            'bloc_key'    => 'bloc_test',
            'order'       => $this->faker->unique()->numberBetween(1, 999),
            'is_required' => false,
            'is_entry_point' => false,
            'is_active'   => true,
            'risk_weight' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function entryPoint(): static
    {
        return $this->state(['is_entry_point' => true]);
    }
}
