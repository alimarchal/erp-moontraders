<?php

namespace App\Providers;

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
        // Implicitly grant "Super Admin" role all permissions
        // This works in the app by using gate-related functions like auth()->user->can() and @can()
        Gate::before(function ($user, $ability) {
            return ($user->hasRole('super-admin') || $user->is_super_admin === 'Yes') ? true : null;
        });

        Gate::define('view-any-report', function ($user) {
            return $user->getAllPermissions()->contains(fn ($p) => str_starts_with($p->name, 'report-'));
        });
    }
}
