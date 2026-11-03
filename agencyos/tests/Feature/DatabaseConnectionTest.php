<?php

namespace Tests\Feature;

use App\Services\WebInstaller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    /**
     * @return array{db_host: string, db_port: int, db_database: string, db_username: string, db_password: string}
     */
    private function database(): array
    {
        return ['db_host' => 'localhost', 'db_port' => 3306, 'db_database' => 'installer_test', 'db_username' => 'installer', 'db_password' => 'private'];
    }

    /** @dataProvider installer_accepts_supported_database_versions_cases */
    public function test_installer_accepts_supported_database_versions(string $version): void
    {
        DB::shouldReceive('purge')->once()->with('installation');
        DB::shouldReceive('selectOne')->once()->with('SELECT VERSION() AS version')->andReturn((object) ['version' => $version]);

        app(WebInstaller::class)->connect($this->database());

        $this->assertSame('installation', config('database.default'));
    }

    /** @dataProvider installer_rejects_unsupported_database_versions_cases */
    public function test_installer_rejects_unsupported_database_versions(string $version): void
    {
        DB::shouldReceive('purge')->once()->with('installation');
        DB::shouldReceive('selectOne')->once()->with('SELECT VERSION() AS version')->andReturn((object) ['version' => $version]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Use MySQL 8.0 or newer, or MariaDB 10.3 or newer.');

        app(WebInstaller::class)->connect($this->database());
    }

    public function test_installer_connection_failure_keeps_credentials_private(): void
    {
        DB::shouldReceive('purge')->once()->with('installation');
        DB::shouldReceive('selectOne')->once()->andThrow(new \RuntimeException('Connection failed for password: private'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Could not connect. Check the database name, host, port, username, password and hosting permissions.');

        app(WebInstaller::class)->connect($this->database());
    }

    /** @dataProvider doctor_uses_the_same_database_version_requirements_cases */
    public function test_doctor_uses_the_same_database_version_requirements(string $driver, string $version, bool $supported): void
    {
        DB::shouldReceive('select')->once()->with('SELECT 1')->andReturn([]);
        DB::shouldReceive('connection->getDriverName')->once()->andReturn($driver);
        DB::shouldReceive('selectOne')->once()->with('SELECT VERSION() AS version')->andReturn((object) ['version' => $version]);

        $this->artisan('agencyos:doctor')
            ->expectsOutput('MySQL 8+ or MariaDB 10.3+: '.($supported ? 'OK' : 'ERROR: unsupported database version'))
            ->assertExitCode($supported ? 0 : 1);
    }
    /** @return array<int, array> */
    public static function installer_accepts_supported_database_versions_cases(): array
    {
        return [
            ['8.0.36'],
            ['10.4.32-MariaDB'],
            ['5.5.5-10.11.8-MariaDB'],
        ];
    }

    /** @return array<int, array> */
    public static function installer_rejects_unsupported_database_versions_cases(): array
    {
        return [
            ['5.7.44'],
            ['10.2.44-MariaDB'],
            ['unknown'],
        ];
    }

    /** @return array<int, array> */
    public static function doctor_uses_the_same_database_version_requirements_cases(): array
    {
        return [
            ['mysql', '8.0.36', true],
            ['mysql', '5.5.5-10.11.8-MariaDB', true],
            ['mariadb', '10.4.32-MariaDB', true],
            ['mysql', '5.7.44', false],
            ['mariadb', '10.2.44-MariaDB', false],
        ];
    }
}
