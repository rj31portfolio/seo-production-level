<?php

namespace App\Jobs;

use App\Models\Agency;
use App\Models\AIRequest;
use App\Models\SeoToolRun;
use App\Models\User;
use App\Services\AI\AIService;
use App\Services\Seo\ToolAccess;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExecuteAITool implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public bool $failOnTimeout = true;

    public function __construct(public int $agencyId, public int $runId)
    {
        $this->onQueue('seo')->afterCommit();
    }

    public function handle(TenantContext $context, AIService $ai, ToolAccess $access): void
    {
        $agency = Agency::findOrFail($this->agencyId);
        $context->run($agency, function () use ($ai, $access): void {
            $run = DB::transaction(function (): ?SeoToolRun {
                $run = SeoToolRun::whereKey($this->runId)->lockForUpdate()->firstOrFail();
                if ($run->status !== 'queued') {
                    return null;
                }$run->update(['status' => 'running', 'started_at' => now()]);

                return $run;
            });
            if (! $run) {
                return;
            }$previous = Auth::user();
            try {
                Auth::setUser(User::findOrFail($run->user_id));
                $access->authorizeRun(Auth::user(), $run->tool, $run->project);
                $data = $ai->generate($run);
                $run->results()->create(['kind' => 'ai_recommendation', 'data' => $data]);
                $run->update(['status' => 'completed', 'summary' => $data['metrics'], 'processed' => 1, 'discovered' => 1, 'finished_at' => now()]);
            } catch (\Throwable $e) {
                $safe = $e instanceof \RuntimeException && ! $e instanceof QueryException ? mb_substr($e->getMessage(), 0, 500) : 'Integration unavailable. Check permissions and provider settings.';
                $run->update(['status' => 'failed', 'error' => $safe, 'finished_at' => now()]);
                AIRequest::where('seo_tool_run_id', $run->id)->update(['status' => 'failed']);
                Log::warning('AI execution failed', ['provider' => 'deepseek', 'agency_id' => $this->agencyId, 'run_id' => $this->runId, 'exception_type' => $e::class]);
            } finally {
                $previous ? Auth::setUser($previous) : Auth::forgetUser();
            }
        });
    }

    public function failed(?\Throwable $exception): void
    {
        $agency = Agency::find($this->agencyId);
        if (! $agency) {
            return;
        }app(TenantContext::class)->run($agency, function (): void {
            SeoToolRun::whereKey($this->runId)->whereIn('status', ['queued', 'running'])->update(['status' => 'failed', 'error' => 'AI queue execution failed.', 'finished_at' => now()]);
            AIRequest::where('seo_tool_run_id', $this->runId)->where('status', 'queued')->update(['status' => 'failed']);
        });
    }
}
