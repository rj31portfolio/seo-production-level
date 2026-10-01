<?php

namespace App\Services\Seo;

class ToolRegistry
{
    public static function all(): array { return config('seo_tools.tools'); }
    public static function get(string $tool): array { return self::all()[$tool] ?? abort(404); }
    public static function settings(): array { return array_merge(config('seo_tools.defaults'),\App\Models\SystemSetting::find('seo_engine')?->value ?? []); }
    public static function enabled(string $tool): bool { return ! in_array($tool,\App\Models\SystemSetting::find('seo_disabled_tools')?->value ?? [],true); }
}
