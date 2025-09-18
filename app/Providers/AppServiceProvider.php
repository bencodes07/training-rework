<?php

namespace App\Providers;

use App\Services\VatsimConnectService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register VATSIM Connect Service
        $this->app->singleton(VatsimConnectService::class, function ($app) {
            return new VatsimConnectService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}