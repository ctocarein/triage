<?php

namespace App\Providers;

use App\Domains\Access\Models\Membership;
use App\Domains\Engagement\Infrastructure\Persistence\Models\Campaign;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Engagement\Infrastructure\Persistence\Models\QrCode;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Organization\Models\Organization;
use App\Domains\Report\Models\Report;
use App\Domains\Engagement\Infrastructure\Persistence\Models\ScanEvent;
use App\Domains\Ambassador\Infrastructure\Providers\AmbassadorServiceProvider;
use App\Domains\Ambassador\Domain\Events\AmbassadorActivated;
use App\Domains\Ambassador\Domain\Events\ReferralCreated;
use App\Domains\Ambassador\Domain\Events\ReferralUsed;
use App\Domains\Ambassador\Application\Listeners\HandleAmbassadorActivated;
use App\Domains\Ambassador\Application\Listeners\HandleReferralCreated;
use App\Domains\Ambassador\Application\Listeners\HandleReferralUsed;
use App\Domains\ExternalApi\Evaluation\Domain\Events\EvaluationCompleted;
use App\Domains\ExternalApi\Integration\Application\HandleEvaluationCompleted;
use App\Domains\Ambassador\Infrastructure\Jobs\RecomputeAmbassadorStatsJob;
use App\Domains\Organization\Infrastructure\Observers\OrganizationDashboardCacheObserver;
use App\Policies\EvaluationSessionPolicy;
use App\Policies\IdentityPolicy;
use App\Policies\MembershipPolicy;
use App\Policies\OrganizationPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Services\Catalog\RoutingRulesValidator;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(AmbassadorServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootFactoryResolution();

        Gate::policy(Identity::class, IdentityPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Membership::class, MembershipPolicy::class);
        Gate::policy(EvaluationSession::class, EvaluationSessionPolicy::class);

        Campaign::observe(OrganizationDashboardCacheObserver::class);
        QrCode::observe(OrganizationDashboardCacheObserver::class);
        ScanEvent::observe(OrganizationDashboardCacheObserver::class);
        Report::observe(OrganizationDashboardCacheObserver::class);
        EvaluationSession::observe(OrganizationDashboardCacheObserver::class);

        Event::listen(AmbassadorActivated::class, HandleAmbassadorActivated::class);
        Event::listen(ReferralCreated::class, HandleReferralCreated::class);
        Event::listen(ReferralUsed::class, HandleReferralUsed::class);
        Event::listen(EvaluationCompleted::class, HandleEvaluationCompleted::class);

        $this->bootAmbassadorRecompute();
        $this->bootRoutingRulesValidation();
    }

    /**
     * Les modèles vivent dans app/Domains/... alors que les factories sont
     * regroupées à plat dans le namespace Database\Factories. Sans ce résolveur,
     * Laravel déduit Database\Factories\Domains\Evaluation\Models\FactFactory
     * depuis App\Domains\Evaluation\Models\Fact et échoue sur « Class not found ».
     */
    private function bootFactoryResolution(): void
    {
        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            $flat = 'Database\\Factories\\'.class_basename($modelName).'Factory';

            if (class_exists($flat)) {
                return $flat;
            }

            // Repli sur la convention Laravel par défaut.
            return 'Database\\Factories\\'.Str::after($modelName, 'App\\').'Factory';
        });
    }

    private function bootAmbassadorRecompute(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        $lastRun = Cache::get('ambassador:recompute:last_run');
        if ($lastRun && now()->diffInMinutes(Carbon::parse($lastRun)) < 1440) {
            return;
        }

        if (! Cache::add('ambassador:recompute:lock', true, now()->addMinutes(10))) {
            return;
        }

        RecomputeAmbassadorStatsJob::dispatchSync();
    }

    private function bootRoutingRulesValidation(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        if (!Schema::hasTable('dynamic_question_templates')) {
            return;
        }

        $questions = DynamicQuestionTemplate::query()
            ->select(['key', 'bloc_key', 'routing_rules'])
            ->get();

        if ($questions->isEmpty()) {
            return;
        }

        $knownQuestionKeys = $questions->pluck('key')->filter()->unique()->values()->all();
        $knownBlocKeys = $questions->pluck('bloc_key')->filter()->unique()->values()->all();

        $validator = app(RoutingRulesValidator::class);
        $errors = [];

        try {
            foreach ($questions as $question) {
                if ($question->routing_rules === null) {
                    continue;
                }

                $errors = array_merge(
                    $errors,
                    $validator->validateRoutingRules(
                        $question->routing_rules,
                        $knownQuestionKeys,
                        $knownBlocKeys,
                        (string) $question->key
                    )
                );
            }

            if (!empty($errors)) {
                Log::error("routing_rules invalides:\n- " . implode("\n- ", $errors));
            }
        } catch (\Throwable $e) {
            Log::error("Erreur lors de la validation des routing_rules: " . $e->getMessage(), [
                'exception' => $e
            ]);
        }
    }
}
