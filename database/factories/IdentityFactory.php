<?php

namespace Database\Factories;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class IdentityFactory extends Factory
{
    protected $model = Identity::class;

    public function definition(): array
    {
        return [
            'id'     => (string) Str::uuid(),
            'kind'   => 'person',
            'status' => 'active',
        ];
    }

    public function system(): static
    {
        return $this->state(['kind' => 'system']);
    }

    public function suspended(): static
    {
        return $this->state(['status' => 'suspended']);
    }

    public function anonymized(): static
    {
        return $this->state(['status' => 'anonymized']);
    }
}
