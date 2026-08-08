<?php

namespace App\Providers;

use App\Models\CrewApplication;
use App\Models\CrewAssignment;
use App\Models\CrewCandidate;
use App\Models\Principal;
use App\Observers\CrewCandidateObserver;
use App\Observers\PrincipalObserver;
use App\Observers\RecruitmentObserver;
use App\Policies\CrewCandidatePolicy;
use App\Policies\PrincipalPolicy;
use App\Repositories\CrewRepositoryInterface;
use App\Repositories\EloquentCrewRepository;
use App\Repositories\ErpnextCrewRepository;
use App\Services\Erpnext\ErpnextClient;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ErpnextClient::class, fn () => ErpnextClient::fromConfig());

        // Swap the crew data source based on ERP HPY availability.
        $this->app->bind(CrewRepositoryInterface::class, function ($app) {
            return $app->make(ErpnextClient::class)->isConfigured()
                ? $app->make(ErpnextCrewRepository::class)
                : $app->make(EloquentCrewRepository::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        CrewCandidate::observe(CrewCandidateObserver::class);
        CrewApplication::observe(RecruitmentObserver::class);
        CrewAssignment::observe(RecruitmentObserver::class);
        Principal::observe(PrincipalObserver::class);

        // The signed-in "user" is an ERP HPY session, not an Eloquent model, so the
        // policies are wired up as guest-allowed gates that read that session.
        // Recruitment and assignments follow the same rules as the Candidate Pool.
        foreach (['candidates', 'applications', 'assignments', 'vessels', 'profitability', 'accounting'] as $subject) {
            foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
                Gate::define("{$subject}.{$ability}", fn (?Authenticatable $user = null) => app(CrewCandidatePolicy::class)->{$ability}());
            }
        }

        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Gate::define("principals.{$ability}", fn (?Authenticatable $user = null) => app(PrincipalPolicy::class)->{$ability}());
        }
    }
}
