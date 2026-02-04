<?php

namespace App\Domains\Ambassador\Infrastructure\Persistence\Repositories;

use App\Domains\Ambassador\Domain\Repositories\AmbassadorBadgeRepository as AmbassadorBadgeRepositoryContract;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Ambassador;
use Illuminate\Support\Collection;

class EloquentAmbassadorBadgeRepository implements AmbassadorBadgeRepositoryContract
{
    public function listForAmbassador(Ambassador $ambassador): Collection
    {
        return $ambassador->badges()->orderByDesc('earned_at')->get();
    }
}
