<?php

namespace App\Services;

use Illuminate\Support\HtmlString;

class PublicAssetUrl
{
    public function resolve(string $path, ?bool $secure = null): string
    {
        if (config('app.asset_url')) {
            return url()->assetFrom(config('app.asset_url'), $path, $secure);
        }

        $documentRoot = request()->server('DOCUMENT_ROOT');
        $resolvedRoot = is_string($documentRoot) && $documentRoot !== '' ? realpath($documentRoot) : false;
        $resolvedPublic = realpath(public_path());

        if ($resolvedRoot !== false && $resolvedPublic !== false) {
            $root = rtrim(str_replace('\\', '/', $resolvedRoot), '/');
            $public = str_replace('\\', '/', $resolvedPublic);

            if (str_starts_with($public, $root.'/')) {
                $prefix = substr($public, strlen($root));

                return url()->assetFrom(request()->getSchemeAndHttpHost().$prefix, $path, $secure);
            }
        }

        return asset($path, $secure);
    }

    public function fonts(): HtmlString
    {
        $path = public_path('build/fonts-manifest.json');
        if (! is_file($path)) {
            return new HtmlString('');
        }

        $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $html = '';
        foreach ($manifest['preloads'] ?? [] as $preload) {
            $html .= '<link rel="preload" as="font" href="'.e($this->resolve('build/'.$preload['file'])).'" crossorigin="anonymous">';
        }

        $style = $manifest['style']['inline'] ?? implode("\n", $manifest['style']['familyStyles'] ?? []);
        $style = preg_replace_callback('/url\(([\'"]?)(\/build\/[^)\'"\s]+)\1\)/', fn (array $matches): string => 'url("'.$this->resolve(ltrim($matches[2], '/')).'")', $style);
        if (! empty($manifest['style']['variables'])) {
            $style .= ':root{'.implode(';', $manifest['style']['variables']).'}';
        }
        if ($style !== '') {
            $html .= '<style>'.$style.'</style>';
        }

        return new HtmlString($html);
    }
}
