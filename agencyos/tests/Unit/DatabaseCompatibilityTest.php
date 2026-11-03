<?php

namespace Tests\Unit;

use App\Services\DatabaseCompatibility;
use PHPUnit\Framework\TestCase;

class DatabaseCompatibilityTest extends TestCase
{
    /** @dataProvider database_version_support_cases */
    public function test_database_version_support(string $version, bool $supported): void
    {
        $this->assertSame($supported, DatabaseCompatibility::supports($version));
    }
    /** @return array<int, array> */
    public static function database_version_support_cases(): array
    {
        return [
            ['8.0.0', true],
            ['8.0.36-0ubuntu0.22.04.1', true],
            ['8.4.0-commercial', true],
            ['9.0.1', true],
            ['5.7.44', false],
            ['10.3.0-MariaDB', true],
            ['10.4.32-MariaDB', true],
            ['10.11.8-MariaDB-0ubuntu0.24.04.1', true],
            ['5.5.5-10.11.8-MariaDB', true],
            ['11.4.2-mariadb-log', true],
            ['10.2.44-MariaDB', false],
            ['5.5.5-10.2.44-MariaDB', false],
            ['MariaDB', false],
            ['unknown', false],
            ['', false],
        ];
    }
}
