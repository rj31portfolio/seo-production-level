<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class WebInstaller
{
    public function directory(): string
    {
        return config('agencyos.installation_directory', storage_path('app/private/installer'));
    }

    public function enabled(): bool
    {
        return is_file($this->directory().'/setup.json') && ! is_file($this->directory().'/installed.lock');
    }

    public function state(): array
    {
        return json_decode(File::get($this->directory().'/setup.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function authorized(string $token): bool
    {
        return $this->enabled() && hash_equals($this->state()['token_hash'], hash('sha256', $token));
    }

    public function requirements(): array
    {
        $checks = ['PHP 8.3 or newer' => version_compare(PHP_VERSION, '8.3', '>='), 'Built frontend assets' => is_file(public_path('build/manifest.json')) && ! is_file(public_path('hot')), 'Configuration cache cleared' => ! app()->configurationIsCached()];
        foreach (['curl', 'dom', 'fileinfo', 'gd', 'mbstring', 'openssl', 'pdo_mysql', 'xml', 'zip'] as $extension) {
            $checks['PHP '.$extension] = extension_loaded($extension);
        }
        foreach ([storage_path(), base_path('bootstrap/cache'), $this->directory()] as $path) {
            $checks[basename($path).' writable'] = is_dir($path) && is_writable($path);
        }
        $environment = app()->environmentFilePath();
        $checks['Environment file writable'] = is_file($environment) ? is_writable($environment) : is_writable(dirname($environment));

        return $checks;
    }

    public function connect(array $data): void
    {
        config(['database.default' => 'installation', 'database.connections.installation' => [
            'driver' => 'mysql', 'host' => $data['db_host'], 'port' => $data['db_port'],
            'database' => $data['db_database'], 'username' => $data['db_username'], 'password' => $data['db_password'] ?? '',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
            'options' => [\PDO::ATTR_TIMEOUT => 5],
        ]]);
        DB::purge('installation');
        try {
            $version = DB::selectOne('SELECT VERSION() AS version')->version;
            if (str_contains($version, 'MariaDB') || version_compare($version, '8.0', '<')) {
                throw ValidationException::withMessages(['db_database' => 'Use a MySQL 8 or newer database.']);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['db_database' => 'Could not connect. Check the database name, host, port, username, password and hosting permissions.']);
        }
    }

    public function assertDatabaseAvailable(array $data): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        if ($schema->getTableListing() === []) {
            return;
        }
        $marker = $this->directory().'/database.json';
        $owned = is_file($marker) && hash_equals(trim(File::get($marker)), $this->fingerprint($data));
        if (! $owned || ($schema->hasTable('users') && DB::table('users')->exists())) {
            throw ValidationException::withMessages(['db_database' => 'Choose an empty database. Existing applications and accounts cannot be overwritten by this installer.']);
        }
    }

    private function fingerprint(array $data): string
    {
        return hash('sha256', json_encode(array_intersect_key($data, array_flip(['db_host', 'db_port', 'db_database', 'db_username']))));
    }

    public function finish(array $database, array $account): void
    {
        $mutex = fopen($this->directory().'/install.mutex', 'c');
        if (! $mutex || ! flock($mutex, LOCK_EX | LOCK_NB)) {
            if (is_resource($mutex)) {
                fclose($mutex);
            }
            throw ValidationException::withMessages(['installation' => 'Installation is already running. Wait a moment before retrying.']);
        }
        try {
            abort_unless($this->enabled(), 404);
            if (in_array(false, $this->requirements(), true)) {
                throw ValidationException::withMessages(['installation' => 'Resolve the server checks before installing.']);
            }
            $this->connect($database);
            $this->assertDatabaseAvailable($database);
            $this->atomicWrite($this->directory().'/database.json', $this->fingerprint($database));
            if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
                throw new \RuntimeException('Migration failed.');
            }
            DB::transaction(function () use ($database, $account): void {
                (new PermissionSeeder)->run();
                $agency = Agency::create(['name' => $account['agency_name'], 'timezone' => $account['timezone'], 'currency' => $account['currency'], 'branding' => [
                    'email' => $account['email'], 'support_email' => $account['support_email'] ?? null, 'address' => $account['address'] ?? null,
                ]]);
                $user = User::create(['name' => $account['name'], 'email' => $account['email'], 'password' => $account['password']]);
                $user->forceFill(['is_super_admin' => true])->save();
                $agency->users()->attach($user, ['role_id' => Role::where('name', 'agency_owner')->firstOrFail()->id]);
                $this->writeEnvironment($database, $account);
            });
            $this->atomicWrite($this->directory().'/installed.lock', now()->toIso8601String());
            File::delete($this->directory().'/setup.json', $this->directory().'/database.json');
        } finally {
            flock($mutex, LOCK_UN);
            fclose($mutex);
        }
    }

    public function writeEnvironment(array $database, array $account): void
    {
        $values = [
            'APP_NAME' => $account['agency_name'], 'APP_ENV' => 'production', 'APP_DEBUG' => 'false',
            'APP_URL' => rtrim($database['app_url'], '/'), 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => $database['db_host'], 'DB_PORT' => (string) $database['db_port'],
            'DB_DATABASE' => $database['db_database'], 'DB_USERNAME' => $database['db_username'], 'DB_PASSWORD' => $database['db_password'] ?? '',
            'SESSION_DRIVER' => 'database', 'SESSION_ENCRYPT' => 'true', 'SESSION_SECURE_COOKIE' => 'true', 'SESSION_DOMAIN' => 'null',
            'CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'database',
            'AGENCYOS_TIMEZONE' => $account['timezone'], 'AGENCYOS_CURRENCY' => $account['currency'],
            'MAIL_MAILER' => empty($account['mail_host']) ? 'log' : 'smtp', 'MAIL_HOST' => $account['mail_host'] ?? '',
            'MAIL_PORT' => (string) ($account['mail_port'] ?? 587), 'MAIL_SCHEME' => $account['mail_scheme'] ?? 'smtp',
            'MAIL_USERNAME' => $account['mail_username'] ?? '', 'MAIL_PASSWORD' => $account['mail_password'] ?? '',
            'MAIL_FROM_ADDRESS' => $account['email'], 'MAIL_FROM_NAME' => $account['agency_name'], 'DEEPSEEK_ENABLED' => 'false',
        ];
        $contents = File::get(base_path('.env.example'));
        foreach ($values as $key => $value) {
            if (preg_match('/[\r\n\x00]/', $value)) {
                throw new \InvalidArgumentException('Environment values cannot contain line breaks.');
            }
            $encoded = in_array($key, ['APP_DEBUG', 'SESSION_ENCRYPT', 'SESSION_SECURE_COOKIE', 'SESSION_DOMAIN', 'DEEPSEEK_ENABLED'], true)
                ? $value : '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
            $line = $key.'='.$encoded;
            $contents = preg_match('/^'.preg_quote($key, '/').'=/m', $contents)
                ? preg_replace_callback('/^'.preg_quote($key, '/').'=.*$/m', fn (): string => $line, $contents)
                : $contents."\n".$line;
        }
        $this->atomicWrite(app()->environmentFilePath(), $contents);
    }

    private function atomicWrite(string $path, string $contents): void
    {
        $temporary = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) === false || ! chmod($temporary, 0600) || ! rename($temporary, $path)) {
                throw new \RuntimeException('Could not save private installation files.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
