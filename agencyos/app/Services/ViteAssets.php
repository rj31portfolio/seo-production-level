<?php

namespace App\Services;

use Illuminate\Foundation\Vite;

class ViteAssets extends Vite
{
    /**
     * @param string $path
     * @param bool|null $secure
     */
    protected function assetPath($path, $secure = null): string
    {
        return app(PublicAssetUrl::class)->resolve($path, $secure);
    }
}
