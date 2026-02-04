<?php

namespace App\Domains\Ambassador\Infrastructure\Providers;

use App\Domains\Ambassador\Domain\Repositories\AmbassadorBadgeRepository;
use App\Domains\Ambassador\Domain\Repositories\AmbassadorRepository;
use App\Domains\Ambassador\Domain\Repositories\ReferralRepository;
use App\Domains\Ambassador\Infrastructure\Persistence\Repositories\EloquentAmbassadorBadgeRepository;
use App\Domains\Ambassador\Infrastructure\Persistence\Repositories\EloquentAmbassadorRepository;
use App\Domains\Ambassador\Infrastructure\Persistence\Repositories\EloquentReferralRepository;
use Illuminate\Support\ServiceProvider;

class AmbassadorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AmbassadorRepository::class, EloquentAmbassadorRepository::class);
        $this->app->bind(ReferralRepository::class, EloquentReferralRepository::class);
        $this->app->bind(AmbassadorBadgeRepository::class, EloquentAmbassadorBadgeRepository::class);
    }
}
