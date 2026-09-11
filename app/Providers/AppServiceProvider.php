<?php

namespace App\Providers;

use App\Auth\CasClient;
use App\Contracts\CasUserResolver;
use App\Events\CasAuthenticated;
use App\Listeners\LogCasAuthentication;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CasClient::class);
        $this->app->bind(CasUserResolver::class, fn ($app) => $app->make(config('cas.user.resolver')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(CasAuthenticated::class, LogCasAuthentication::class);
    }
}
