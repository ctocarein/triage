<?php

namespace App\Domains\Ambassador\Infrastructure\Persistence\Repositories;

use App\Domains\Ambassador\Domain\Repositories\AmbassadorRepository as AmbassadorRepositoryContract;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Ambassador;

class EloquentAmbassadorRepository implements AmbassadorRepositoryContract
{
    public function findByUserId(string $userId): ?Ambassador
    {
        return Ambassador::query()->where('user_id', $userId)->first();
    }

    public function createForUser(string $userId): Ambassador
    {
        return Ambassador::query()->create([
            'user_id' => $userId,
            'status' => 'active',
            'activated_at' => now(),
        ]);
    }
}
