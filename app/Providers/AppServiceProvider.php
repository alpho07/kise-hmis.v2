<?php

namespace App\Providers;

use App\Models\ServiceSession;
use App\Observers\ServiceSessionObserver;
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
        ServiceSession::observe(ServiceSessionObserver::class);
    }
}
