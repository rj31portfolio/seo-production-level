<?php

namespace App\Console\Commands;

use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Command;

class InstallAgency extends Command
{
    protected $signature = 'agencyos:install {--force : Apply installation steps in production}';

    protected $description = 'Initialize AgencyOS without replacing existing keys, accounts, or business data';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Use --force to install in production after configuring your private .env and database backup.');

            return self::FAILURE;
        }
        if (! is_file(base_path('.env'))) {
            $this->error('Copy .env.example to .env and configure your database and application URL first.');

            return self::FAILURE;
        }
        if (! config('app.key')) {
            if (app()->configurationIsCached()) {
                $this->error('Clear cached configuration with config:clear before generating the initial application key.');

                return self::FAILURE;
            }
            if ($this->call('key:generate', ['--force' => true]) !== self::SUCCESS) {
                return self::FAILURE;
            }
        } else {
            $this->info('Existing application key preserved.');
        }
        if ($this->call('migrate', ['--force' => true, '--no-interaction' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }
        if ($this->call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true, '--no-interaction' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }
        $this->info('AgencyOS database and permissions initialized. No default accounts were created.');
        $this->line('Next: build assets with npm ci --ignore-scripts and npm run build.');
        $this->line('Create your administrator with php artisan agencyos:admin, then register your agency at /register.');
        $this->line('Configure SMTP, a supervised seo,default queue worker, and the scheduler.');
        $this->line('Before launch run php artisan agencyos:doctor --production.');

        return self::SUCCESS;
    }
}
