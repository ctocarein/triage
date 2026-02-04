<?php

namespace App\Domains\Ambassador\Application\Queries;

use App\Domains\Ambassador\Domain\Repositories\AmbassadorRepository;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Ambassador;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class GetAmbassadorProfileQuery
{
    public function __construct(private readonly AmbassadorRepository $repository)
    {
    }

    public function handle(Identity $actor): Ambassador
    {
        $ambassador = $this->repository->findByUserId($actor->id);

        if ($ambassador === null) {
            abort(404, 'Ambassador profile not found.');
        }

        return $ambassador;
    }
}
