<?php

namespace App\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
use Schema;


class UserTrackingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Blueprint::macro('userTracking', function () {
            $this->unsignedBigInteger('created_by')->nullable();
            $this->unsignedBigInteger('updated_by')->nullable();

            $this->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $this->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }
}
