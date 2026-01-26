<?php

namespace App\Providers;

use App\Domains\Access\Models\Membership;
use App\Domains\Engagement\Models\Campaign;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Engagement\Models\QrCode;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Organization\Models\Organization;
use App\Domains\Report\Models\Report;
use App\Domains\Engagement\Models\ScanEvent;
use App\Observers\OrganizationDashboardCacheObserver;
use App\Policies\IdentityPolicy;
use App\Policies\MembershipPolicy;
use App\Policies\OrganizationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Identity::class, IdentityPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Membership::class, MembershipPolicy::class);

        Campaign::observe(OrganizationDashboardCacheObserver::class);
        QrCode::observe(OrganizationDashboardCacheObserver::class);
        ScanEvent::observe(OrganizationDashboardCacheObserver::class);
        Report::observe(OrganizationDashboardCacheObserver::class);
        EvaluationSession::observe(OrganizationDashboardCacheObserver::class);
    }
}
