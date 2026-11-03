<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class Dev extends Command
{
    protected $signature = 'dev';

    protected $description = 'Run the development server, SEO queue, scheduler and Vite';

    /** @return array<string, string> */
    public function processes(): array
    {
        $artisan = '"'.PHP_BINARY.'" artisan ';

        return [
            'server' => $artisan.'serve',
            'queue' => $artisan.'queue:work --queue=seo,default --sleep=3 --tries=1 --timeout=1800 --memory=384',
            'scheduler' => $artisan.'schedule:work',
            'vite' => 'npm run dev',
        ];
    }

    public function handle(): int
    {
        $commands = $this->processes();
        $process = new Process(['npx', 'concurrently', '--kill-others', '--names='.implode(',', array_keys($commands)), ...array_values($commands)], base_path());
        $process->setTimeout(null);

        return $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });
    }
}
