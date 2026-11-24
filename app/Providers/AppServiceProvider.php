<?php

namespace App\Providers;

use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Policies\AssetPolicy;
use App\Policies\MaintenancePlanPolicy;
use App\Policies\UserPolicy;
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
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(MaintenancePlan::class, MaintenancePlanPolicy::class);
    }
}
