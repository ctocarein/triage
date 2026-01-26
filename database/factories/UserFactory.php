<?php

namespace Database\Factories;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Legacy factory name kept for compatibility.
 */
class UserFactory extends Factory
{
    protected $model = Identity::class;

    public function definition(): array
    {
        return [
            'kind' => 'person',
            'status' => 'active',
        ];
    }
}