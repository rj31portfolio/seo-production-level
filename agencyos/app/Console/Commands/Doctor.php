<?php

namespace App\Console\Commands;

use App\Services\DatabaseCompatibility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Doctor extends Command
{
    protected $signature = 'agencyos:doctor {--production : Fail when production configuration is incomplete}';

    protected $description = 'Check runtime and deployment prerequisites without printing credentials';

    public function handle(): int
    {
        $ok = version_compare(PHP_VERSION, '8.0.2', '>=');
        $this->line('PHP '.PHP_VERSION.': '.($ok ? 'OK' : 'ERROR: 8.0.2+ required'));
        foreach (['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'gd', 'hash', 'mbstring', 'openssl', 'pcre', 'pdo', 'session', 'tokenizer', 'xml', 'zip'] as $ext) {
            $loaded = extension_loaded($ext);
            $this->line($ext.': '.($loaded ? 'OK' : 'ERROR'));
            $ok = $ok && $loaded;
        }
        foreach ([storage_path(), base_path('bootstrap/cache')] as $path) {
            $writable = is_writable($path);
            $this->line(basename($path).': '.($writable ? 'writable' : 'ERROR: not writable'));
            $ok = $ok && $writable;
        }
        $driver = null;
        try {
            DB::select('SELECT 1');
            $driver = DB::connection()->getDriverName();
            $this->line('Database connection: OK ('.$driver.')');
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $version = DB::selectOne('SELECT VERSION() AS version')->version;
                $supported = DatabaseCompatibility::supports($version);
                $this->line('MySQL 8+ or MariaDB 10.3+: '.($supported ? 'OK' : 'ERROR: unsupported database version'));
                $ok = $ok && $supported;
            } else {
                $this->warn('SQLite supports local tests; MySQL/MariaDB production validation is outstanding.');
            }
        } catch (\Throwable $e) {
            $this->error('Database connection failed. Check private environment configuration.');
            $ok = false;
        }
        if (! config('app.key')) {
            $this->error('APP_KEY is missing.');
            $ok = false;
        }
        if (config('app.debug')) {
            $this->warn('Debug mode is enabled. Disable it for production.');
        }
        if (config('mail.default') === 'log') {
            $this->warn('Mail uses the local log driver. Configure SMTP for password-reset delivery.');
        }
        if ($this->option('production')) {
            $checks = [
                'Production environment' => app()->environment('production'),
                'Debug disabled' => ! config('app.debug'),
                'HTTPS application URL' => str_starts_with((string) config('app.url'), 'https://'),
                'Secure session cookies' => (bool) config('session.secure'),
                'Deliverable mail transport' => ! in_array(config('mail.default'), ['log', 'array'], true),
                'Asynchronous queue backend' => ! in_array(config('queue.default'), ['sync', 'null'], true),
                'Production assets built' => is_file(public_path('build/manifest.json')) && ! is_file(public_path('hot')),
                'MySQL/MariaDB production database' => in_array($driver, ['mysql', 'mariadb'], true),
            ];
            try {
                $migrator = app('migrator');
                $checks['All migrations applied'] = $migrator->repositoryExists()
                    && array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->getRepository()->getRan()) === [];
                $checks['Permissions initialized'] = DB::table('roles')->where('name', 'agency_owner')->exists();
                $checks['Platform administrator exists'] = DB::table('users')->where('is_super_admin', true)->exists();
            } catch (\Throwable) {
                $checks['Database installation complete'] = false;
            }
            foreach ($checks as $label => $passed) {
                $this->line($label.': '.($passed ? 'OK' : 'ERROR'));
                $ok = $ok && $passed;
            }
        }
        $this->line('Queue backend: '.config('queue.default').' (worker execution must be configured separately)');
        $this->line('Scheduler execution must be configured separately; no heartbeat is currently tracked.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
