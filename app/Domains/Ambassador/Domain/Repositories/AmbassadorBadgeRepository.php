<?php

namespace App\Domains\Ambassador\Domain\Repositories;

use App\Domains\Ambassador\Infrastructure\Persistence\Models\Ambassador;
use Illuminate\Support\Collection;

interface AmbassadorBadgeRepository
{
    public function listForAmbassador(Ambassador $ambassador): Collection;
}
