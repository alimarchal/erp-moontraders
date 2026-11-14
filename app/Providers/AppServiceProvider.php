<?php

namespace App\Providers;

use Google\Client;
use Google\Service\Drive;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;

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
        // Post / reverse / revert actions confirmed with the user's password: cap attempts so the
        // password cannot be brute-forced from a logged-in session. Counted per user and per action.
        RateLimiter::for('password-confirm', function (Request $request) {
            return Limit::perMinute(10)->by(($request->user()?->id ?? $request->ip()).'|'.$request->route()?->getName());
        });

        // Implicitly grant "Super Admin" role all permissions
        // This works in the app by using gate-related functions like auth()->user->can() and @can()
        Gate::before(function ($user, $ability) {
            return ($user->hasRole('super-admin') || $user->is_super_admin === 'Yes') ? true : null;
        });

        Gate::define('view-any-report', function ($user) {
            return $user->getAllPermissions()->contains(fn ($p) => str_starts_with($p->name, 'report-'));
        });

        Storage::extend('google', function ($app, $config) {
            $client = new Client;
            $client->setClientId($config['clientId']);
            $client->setClientSecret($config['clientSecret']);
            $client->refreshToken($config['refreshToken']);

            $service = new Drive($client);
            $adapter = new GoogleDriveAdapter($service, $config['folder'] ?? '/');
            $driver = new Filesystem($adapter);
            $disk = new FilesystemAdapter($driver, $adapter);

            // Auto-create the backup subfolder so spatie's reachability
            // check doesn't fail on a non-existent directory.
            try {
                if (! $disk->directoryExists($config['backup_name'])) {
                    $disk->makeDirectory($config['backup_name']);
                }
            } catch (\Throwable) {
                // Already exists or transient error — safe to ignore.
            }

            return $disk;
        });
    }
}
