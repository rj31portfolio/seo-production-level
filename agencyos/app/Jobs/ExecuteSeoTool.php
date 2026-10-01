<?php

namespace App\Jobs;

use App\Models\Agency;
use App\Models\SeoToolRun;
use App\Models\User;
use App\Services\Seo\MonitoringService;
use App\Services\Seo\NetworkEngine;
use App\Services\Seo\ToolAccess;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExecuteSeoTool implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public bool $failOnTimeout = true;

    public function __construct(public int $agencyId, public int $runId)
    {
        $this->onQueue('seo')->afterCommit();
    }

    public function handle(TenantContext $context, NetworkEngine $engine, ToolAccess $access): void
    {
        $agency = Agency::findOrFail($this->agencyId);
        $context->run($agency, function () use ($engine, $access) {
            $run = DB::transaction(function () {
                $run = SeoToolRun::lockForUpdate()->findOrFail($this->runId);
                if ($run->status !== 'queued') {
                    return null;
                }$run->update(['status' => 'running', 'started_at' => now()]);

                return $run;
            });
            if (! $run) {
                return;
            }$previous = Auth::user();
            Auth::setUser(User::findOrFail($run->user_id));
            try {
                $access->authorizeRun(Auth::user(), $run->tool, $run->project);
                $engine->execute($run);
                $run->update(['status' => 'completed', 'finished_at' => now()]);
            } catch (\Throwable $e) {
                $safe = $e instanceof \RuntimeException && ! $e instanceof QueryException && ! $e instanceof HttpException ? mb_substr($e->getMessage(), 0, 500) : 'Tool execution failed. Check access, service status, and private system logs.';
                $run->update(['status' => 'failed', 'error' => $safe, 'finished_at' => now()]);
                Log::warning('SEO tool execution failed', ['agency_id' => $this->agencyId, 'run_id' => $this->runId, 'exception_type' => $e::class]);
            } finally {
                if ($previous) {
                    Auth::setUser($previous);
                } else {
                    Auth::forgetUser();
                }
            }
            app(MonitoringService::class)->completed($run);
        });
    }

    public function failed(?\Throwable $exception): void
    {
        $agency = Agency::find($this->agencyId);
        if (! $agency) {
            return;
        }
        app(TenantContext::class)->run($agency, function () {
            SeoToolRun::whereKey($this->runId)->whereIn('status', ['queued', 'running'])->update(['status' => 'failed', 'error' => 'Queue job failed or exceeded its time limit.', 'finished_at' => now()]);
        });
    }
}
