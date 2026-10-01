<?php

namespace App\Services\Seo;

use App\Models\SystemSetting;

class ToolRegistry
{
    public static function all(): array
    {
        return config('seo_tools.tools');
    }

    public static function get(string $tool): array
    {
        return self::all()[$tool] ?? abort(404);
    }

    public static function settings(): array
    {
        return array_merge(config('seo_tools.defaults'), SystemSetting::find('seo_engine')?->value ?? []);
    }

    public static function enabled(string $tool): bool
    {
        return ! in_array($tool, SystemSetting::find('seo_disabled_tools')?->value ?? [], true);
    }
}
