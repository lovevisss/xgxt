<?php

namespace Zufedfc\LaravelCas\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class InstallCommand extends Command
{
    protected $signature = 'cas:install {--force : Overwrite an existing CAS configuration file}';

    protected $description = 'Publish the Laravel CAS configuration and optional user migration';

    public function handle(): int
    {
        $arguments = [
            '--tag' => 'cas-config',
        ];

        if ($this->option('force')) {
            $arguments['--force'] = true;
        }

        $this->call('vendor:publish', $arguments);

        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'cas_username')) {
            $this->call('vendor:publish', ['--tag' => 'cas-migrations']);
            $this->components->info('Published the optional cas_username migration.');
        } else {
            $this->components->info('users.cas_username already exists; migration publishing was skipped.');
        }

        $baseUrl = rtrim((string) config('app.url'), '/');
        $prefix = trim((string) config('cas.routes.prefix', 'sso'), '/');

        $this->newLine();
        $this->components->info('CAS package installed.');
        $this->line("Login callback: {$baseUrl}/{$prefix}/login");
        $this->line("Logout callback: {$baseUrl}/{$prefix}/logout");
        $this->line("Single logout URL: {$baseUrl}/{$prefix}/slo");
        $this->newLine();
        $this->line('Set CAS_ENABLED=true and CAS_SERVER_URL in .env, then run php artisan migrate if a migration was published.');

        return self::SUCCESS;
    }
}
