<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\SeoMonitoring;
use App\Models\SeoToolRun;
use App\Models\User;
use App\Services\Seo\MonitoringService;
use App\Services\Seo\ToolAccess;
use App\Services\Seo\ToolRunner;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RunSeoMonitors extends Command
{
    protected $signature = 'agencyos:seo-monitors';

    protected $description = 'Queue due permitted website monitoring checks';

    public function handle(TenantContext $context, ToolRunner $runner, ToolAccess $access, MonitoringService $monitoring): int
    {
        $queued = 0;
        foreach (Agency::where('status', 'active')->lazyById(100) as $agency) {
            $context->run($agency, function () use ($runner, $access, $monitoring, &$queued): void {
                foreach (SeoMonitoring::whereNotNull('pending_run_id')->lazyById(100) as $monitor) {
                    $run = SeoToolRun::find($monitor->pending_run_id);
                    if ($run && in_array($run->status, ['completed', 'failed'], true)) {
                        $monitoring->completed($run);
                    }
                }
                foreach (SeoMonitoring::where('enabled', true)->whereNull('pending_run_id')->where('next_due_at', '<=', now())->lazyById(100) as $monitor) {
                    $previous = Auth::user();
                    try {
                        Auth::setUser(User::findOrFail($monitor->user_id));
                        DB::transaction(function () use ($monitor, $runner, $access, &$queued): void {
                            $monitor = SeoMonitoring::whereKey($monitor->id)->lockForUpdate()->firstOrFail();
                            if ($monitor->pending_run_id || ! $monitor->enabled || $monitor->next_due_at->isFuture()) {
                                return;
                            }$access->authorizeRun(Auth::user(), 'website-monitor', $monitor->project);
                            $run = $runner->start('page-score', ['url' => $monitor->project->website->url], $monitor->project);
                            $monitor->update(['pending_run_id' => $run->id, 'next_due_at' => now()->addMinutes($monitor->interval_minutes), 'last_error' => null]);
                            $queued++;
                        });
                    } catch (\Throwable) {
                        $monitor->update(['last_error' => 'Check could not be queued. Review tool access, client service status, limits and system logs.', 'next_due_at' => now()->addMinutes($monitor->interval_minutes)]);
                    } finally {
                        $previous ? Auth::setUser($previous) : Auth::forgetUser();
                    }
                }
            });
        }$this->info($queued.' monitoring checks queued.');

        return self::SUCCESS;
    }
}
