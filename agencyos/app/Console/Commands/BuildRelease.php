<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use ZipArchive;

class BuildRelease extends Command
{
    protected $signature = 'agencyos:release {--composer= : Path to a Composer PHAR, if Composer is not on PATH}';

    protected $description = 'Build a clean upload-ready ZIP with production dependencies and a private two-step installer';

    public function handle(): int
    {
        if (! extension_loaded('zip') || ! is_file(public_path('build/manifest.json')) || is_file(public_path('hot'))) {
            $this->error('Enable PHP ZIP and run npm run build before packaging. Remove public/hot if a dev server left it behind.');

            return self::FAILURE;
        }
        $output = storage_path('app/releases');
        $stage = $output.'/stage-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($stage);
        try {
            foreach (['app', 'config', 'routes', 'resources/views', 'database/migrations', 'database/seeders', 'vendor', 'public/build'] as $directory) {
                if (! File::copyDirectory(base_path($directory), $stage.'/'.$directory)) {
                    throw new \RuntimeException('Could not stage '.$directory);
                }
            }
            foreach (['artisan', 'composer.json', 'composer.lock', '.env.example', 'INSTALLATION.md', 'README.md', 'bootstrap/app.php', 'bootstrap/providers.php', 'public/index.php', 'public/.htaccess', 'public/robots.txt', 'public/favicon.ico'] as $file) {
                File::ensureDirectoryExists(dirname($stage.'/'.$file));
                File::copy(base_path($file), $stage.'/'.$file);
            }
            foreach (['bootstrap/cache', 'storage/app/private/installer', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $directory) {
                File::ensureDirectoryExists($stage.'/'.$directory);
            }
            $composer = $this->option('composer') ? [PHP_BINARY, $this->option('composer')] : ['composer'];
            $process = new Process([...$composer, 'install', '--no-dev', '--optimize-autoloader', '--no-scripts', '--no-interaction', '--prefer-dist'], $stage, ['COMPOSER_DISABLE_NETWORK' => '1']);
            $process->setTimeout(240);
            $process->mustRun(function (string $type, string $buffer): void {
                $this->output->write($buffer);
            });
            $token = bin2hex(random_bytes(24));
            $state = ['token_hash' => hash('sha256', $token), 'key' => 'base64:'.base64_encode(random_bytes(32))];
            File::put($stage.'/storage/app/private/installer/setup.json', json_encode($state, JSON_PRETTY_PRINT));
            $zipPath = $output.'/agencyos-live-'.date('Ymd-His').'.zip';
            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new \RuntimeException('Could not create the release ZIP.');
            }
            foreach (File::allFiles($stage, true) as $file) {
                $relative = str_replace('\\', '/', $file->getRelativePathname());
                if (! $zip->addFile($file->getPathname(), 'agencyos/'.$relative)) {
                    throw new \RuntimeException('Could not archive '.$relative);
                }
            }
            foreach (File::directories($stage) as $directory) {
                $zip->addEmptyDir('agencyos/'.basename($directory));
            }
            foreach (['bootstrap/cache', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $directory) {
                $zip->addEmptyDir('agencyos/'.$directory);
            }
            if (! $zip->close()) {
                throw new \RuntimeException('Could not finish the release ZIP.');
            }
            $keyPath = substr($zipPath, 0, -4).'-setup-key.txt';
            File::put($keyPath, "PRIVATE SETUP KEY\n".$token."\n\nUpload the ZIP only. Keep this file on your computer.\nOpen https://YOUR-DOMAIN/install and use this key in step 1.\n");
            chmod($keyPath, 0600);
            $this->info('Release ZIP: '.$zipPath);
            $this->info('Private setup key: '.$keyPath);
            $this->line('The package excludes .env, logs, databases, uploads, tests, node_modules and development dependencies.');

            return self::SUCCESS;
        } finally {
            $resolvedStage = realpath($stage);
            $resolvedOutput = realpath($output);
            if ($resolvedStage && $resolvedOutput && str_starts_with($resolvedStage, $resolvedOutput.DIRECTORY_SEPARATOR)) {
                File::deleteDirectory($resolvedStage);
            }
        }
    }
}
