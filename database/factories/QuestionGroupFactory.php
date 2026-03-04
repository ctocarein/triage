<?php

namespace Database\Factories;

use App\Domains\Evaluation\Models\QuestionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionGroupFactory extends Factory
{
    protected $model = QuestionGroup::class;

    public function definition(): array
    {
        return [
            'key'       => 'group_' . $this->faker->unique()->lexify('????'),
            'label'     => $this->faker->words(3, true),
            'order'     => $this->faker->numberBetween(1, 100),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
