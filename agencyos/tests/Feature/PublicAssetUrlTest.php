<?php

namespace Tests\Feature;

use App\Services\PublicAssetUrl;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicAssetUrlTest extends TestCase
{
    private string $temporary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporary = storage_path('framework/testing/public-assets-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->temporary.'/public/build/assets');
        File::put($this->temporary.'/public/build/manifest.json', json_encode([
            'resources/css/app.css' => ['file' => 'assets/app.css', 'src' => 'resources/css/app.css', 'isEntry' => true],
            'resources/js/app.js' => ['file' => 'assets/app.js', 'src' => 'resources/js/app.js', 'isEntry' => true],
        ]));
        File::put($this->temporary.'/public/build/fonts-manifest.json', json_encode([
            'version' => 1, 'families' => [],
            'style' => ['inline' => '@font-face{src:url("/build/assets/font.woff2")}'],
            'preloads' => [['alias' => 'instrument-sans', 'file' => 'assets/font.woff2', 'as' => 'font']],
        ]));
        app()->instance('path.public', $this->temporary.'/public');
        config(['app.asset_url' => null]);
        Route::get('/_asset-preview', fn (): string => app(PublicAssetUrl::class)->fonts()->toHtml().app(Vite::class)(['resources/css/app.css', 'resources/js/app.js'])->toHtml());
    }

    protected function tearDown(): void
    {
        if (isset($this->temporary) && str_starts_with(realpath($this->temporary), realpath(storage_path('framework/testing')).DIRECTORY_SEPARATOR)) {
            File::deleteDirectory($this->temporary);
        }

        parent::tearDown();
    }

    /** @dataProvider css_javascript_and_fonts_follow_the_hosting_document_root_cases */
    public function test_css_javascript_and_fonts_follow_the_hosting_document_root(bool $servesPublicDirectory, string $prefix): void
    {
        $this->withServerVariables(['DOCUMENT_ROOT' => $this->temporary.($servesPublicDirectory ? '/public' : '')])
            ->get('https://agency.example/_asset-preview')
            ->assertOk()
            ->assertSee('href="https://agency.example'.$prefix.'/build/assets/app.css"', false)
            ->assertSee('src="https://agency.example'.$prefix.'/build/assets/app.js"', false)
            ->assertSee('href="https://agency.example'.$prefix.'/build/assets/font.woff2"', false)
            ->assertSee('url("https://agency.example'.$prefix.'/build/assets/font.woff2")', false);
    }

    public function test_public_is_not_duplicated_when_already_in_the_request_base_path(): void
    {
        File::put($this->temporary.'/public/index.php', '');
        $this->withServerVariables([
            'DOCUMENT_ROOT' => $this->temporary,
            'SCRIPT_NAME' => '/public/index.php',
            'SCRIPT_FILENAME' => $this->temporary.'/public/index.php',
            'PHP_SELF' => '/public/index.php',
        ])->get('https://agency.example/public/_asset-preview')
            ->assertOk()
            ->assertSee('https://agency.example/public/build/assets/app.css', false)
            ->assertDontSee('/public/public/', false);
    }

    public function test_explicit_asset_origin_overrides_automatic_detection(): void
    {
        config(['app.asset_url' => 'https://cdn.example/static']);

        $this->withServerVariables(['DOCUMENT_ROOT' => $this->temporary])
            ->get('https://agency.example/_asset-preview')
            ->assertOk()
            ->assertSee('https://cdn.example/static/build/assets/app.css', false)
            ->assertSee('url("https://cdn.example/static/build/assets/font.woff2")', false)
            ->assertDontSee('agency.example/public/build', false);
    }

    public function test_missing_document_root_uses_standard_asset_urls(): void
    {
        $this->withServerVariables(['DOCUMENT_ROOT' => ''])
            ->get('https://agency.example/_asset-preview')
            ->assertOk()
            ->assertSee('https://agency.example/build/assets/app.css', false);
    }

    public function test_an_unrelated_document_root_does_not_add_a_public_prefix(): void
    {
        File::ensureDirectoryExists($this->temporary.'/pub');
        $this->withServerVariables(['DOCUMENT_ROOT' => $this->temporary.'/pub'])
            ->get('https://agency.example/_asset-preview')
            ->assertOk()
            ->assertSee('https://agency.example/build/assets/app.css', false);
    }

    public function test_development_asset_urls_still_use_the_vite_server(): void
    {
        File::put(public_path('hot'), 'http://localhost:5173');

        $this->withServerVariables(['DOCUMENT_ROOT' => $this->temporary])
            ->get('https://agency.example/_asset-preview')
            ->assertOk()
            ->assertSee('http://localhost:5173/@vite/client', false)
            ->assertSee('http://localhost:5173/resources/css/app.css', false);
    }
    /** @return array<int, array> */
    public static function css_javascript_and_fonts_follow_the_hosting_document_root_cases(): array
    {
        return [
            [false, '/public'],
            [true, ''],
        ];
    }
}
