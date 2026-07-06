<?php

namespace App\Providers;

use App\Listeners\LogCasAuthentication;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Zufedfc\LaravelCas\Events\CasAuthenticated;

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
        Event::listen(CasAuthenticated::class, LogCasAuthentication::class);
    }
}
