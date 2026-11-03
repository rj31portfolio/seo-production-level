<?php

namespace App\Services;

class DatabaseCompatibility
{
    public static function supports(string $version): bool
    {
        if (stripos($version, 'MariaDB') !== false) {
            return preg_match('/(\d+\.\d+(?:\.\d+)?)-MariaDB\b/i', $version, $matches) === 1
                && version_compare($matches[1], '10.3.0', '>=');
        }

        return preg_match('/^(\d+\.\d+(?:\.\d+)?)(?:[-+].*)?$/', $version, $matches) === 1
            && version_compare($matches[1], '8.0.0', '>=');
    }
}
