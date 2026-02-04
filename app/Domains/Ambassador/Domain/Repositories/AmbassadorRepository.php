<?php

namespace App\Domains\Ambassador\Domain\Repositories;

use App\Domains\Ambassador\Infrastructure\Persistence\Models\Ambassador;

interface AmbassadorRepository
{
    public function findByUserId(string $userId): ?Ambassador;

    public function createForUser(string $userId): Ambassador;
}
