<?php

namespace Database\Factories;

use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EvaluationSessionFactory extends Factory
{
    protected $model = EvaluationSession::class;

    public function definition(): array
    {
        return [
            'identity_id' => Identity::factory(),
            'public_id'   => (string) Str::uuid(),
            'status'      => 'in_progress',
            'type'        => 'complete',
            'score'       => 0,
            'started_at'  => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status'       => 'completed',
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status'       => 'cancelled',
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }
}
