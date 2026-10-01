<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Doctor extends Command
{
    protected $signature = 'agencyos:doctor';

    protected $description = 'Check runtime and deployment prerequisites without printing credentials';

    public function handle(): int
    {
        $ok = version_compare(PHP_VERSION, '8.3', '>=');
        $this->line('PHP '.PHP_VERSION.': '.($ok ? 'OK' : 'ERROR: 8.3+ required'));
        foreach (['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring', 'openssl', 'pcre', 'pdo', 'session', 'tokenizer', 'xml', 'zip'] as $ext) {
            $loaded = extension_loaded($ext);
            $this->line($ext.': '.($loaded ? 'OK' : 'ERROR'));
            $ok = $ok && $loaded;
        }
        foreach ([storage_path(), base_path('bootstrap/cache')] as $path) {
            $writable = is_writable($path);
            $this->line(basename($path).': '.($writable ? 'writable' : 'ERROR: not writable'));
            $ok = $ok && $writable;
        }
        try {
            DB::select('SELECT 1');
            $driver = DB::connection()->getDriverName();
            $this->line('Database connection: OK ('.$driver.')');
            if ($driver === 'mysql') {
                $version = DB::selectOne('SELECT VERSION() AS version')->version;
                $mysql = ! str_contains($version, 'MariaDB') && version_compare($version, '8.0', '>=');
                $this->line('MySQL 8+: '.($mysql ? 'OK' : 'ERROR: requested MySQL 8+ is required for production validation'));
                $ok = $ok && $mysql;
            } else {
                $this->warn('SQLite supports local tests; MySQL 8 production validation is outstanding.');
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
        $this->line('Queue backend: '.config('queue.default').' (worker execution must be configured separately)');
        $this->line('Scheduler execution must be configured separately; no heartbeat is currently tracked.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
