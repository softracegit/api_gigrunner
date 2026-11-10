<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
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
        // Bearer token auth for private channel subscription (app + playground).
        Broadcast::routes([
            'prefix' => 'api',
            'middleware' => ['auth:sanctum'],
        ]);
    }
}
