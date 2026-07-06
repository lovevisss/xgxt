<?php

namespace Zufedfc\LaravelCas;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Zufedfc\LaravelCas\Console\InstallCommand;
use Zufedfc\LaravelCas\Contracts\CasUserResolver;
use Zufedfc\LaravelCas\Http\Middleware\EnsureCasAuthenticated;
use Zufedfc\LaravelCas\Resolvers\DefaultCasUserResolver;

class CasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cas.php', 'cas');

        $this->app->singleton(CasClient::class);
        $this->app->bind(CasUserResolver::class, function ($app) {
            $resolver = config('cas.user.resolver', DefaultCasUserResolver::class);

            return $app->make($resolver);
        });
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('cas.auth', EnsureCasAuthenticated::class);
        VerifyCsrfToken::except(trim((string) config('cas.routes.prefix', 'sso'), '/').'/slo');

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->publishes([
            __DIR__.'/../config/cas.php' => config_path('cas.php'),
        ], 'cas-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations/add_cas_username_to_users_table.php.stub'
                => database_path('migrations/2026_07_05_000000_add_cas_username_to_users_table.php'),
        ], 'cas-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);
        }
    }
}
