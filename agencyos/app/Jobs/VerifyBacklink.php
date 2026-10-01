<?php

namespace App\Jobs;

use App\Models\Agency;
use App\Models\BacklinkVerification;
use App\Models\User;
use App\Services\Seo\NetworkEngine;
use App\Services\Seo\PageAnalyzer;
use App\Services\Seo\ToolAccess;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VerifyBacklink implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public bool $failOnTimeout = true;

    public function __construct(public int $agencyId, public int $verificationId)
    {
        $this->onQueue('seo');
    }

    public function handle(TenantContext $context, NetworkEngine $network, PageAnalyzer $pages, ToolAccess $access): void
    {
        $agency = Agency::find($this->agencyId);
        if (! $agency || $agency->status !== 'active') {
            $this->failed(new \RuntimeException('Agency is inactive.'));

            return;
        }
        $context->run($agency, function () use ($network, $pages, $access): void {
            $verification = DB::transaction(function (): ?BacklinkVerification {
                $v = BacklinkVerification::whereKey($this->verificationId)->lockForUpdate()->first();
                if (! $v || $v->status !== 'queued') {
                    return null;
                }$v->update(['status' => 'running']);

                return $v;
            });
            if (! $verification) {
                return;
            }$previous = Auth::user();
            try {
                $user = User::findOrFail($verification->user_id);
                Auth::setUser($user);
                $backlink = $verification->backlink;
                $access->authorizeRun($user, 'backlink-manager', $backlink->project);
                $response = $network->fetchAllowed($backlink->source_url);
                if ($response['status'] !== 200 || ! str_contains(strtolower($response['headers']['content-type'] ?? ''), 'html')) {
                    throw new \RuntimeException('Source page is unavailable or is not HTML. HTTP '.$response['status'].'.');
                }
                $page = $pages->analyze($response);
                $matches = array_values(array_filter($page['links'], fn ($link) => $link['url'] === $backlink->target_url));
                $status = $matches ? 'found' : 'not_found';
                $verification->update(['status' => 'completed', 'data' => ['http_status' => $response['status'], 'final_source_url' => $response['url'], 'status' => $status, 'matches' => $matches, 'source' => 'Internal crawler', 'note' => 'Exact normalized target matching within fetched HTML. JavaScript links, redirects at the target and site-wide backlink completeness are not established. DoFollow means no nofollow/sponsored/ugc relation was detected.'], 'checked_at' => now()]);
                $backlink->update(['status' => $status]);
            } catch (\Throwable $e) {
                $verification->update(['status' => 'failed', 'error' => $e instanceof \RuntimeException && ! $e instanceof QueryException ? mb_substr($e->getMessage(), 0, 500) : 'Backlink could not be verified.', 'checked_at' => now()]);
                $verification->backlink->update(['status' => 'failed']);
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
            $verification = BacklinkVerification::find($this->verificationId);
            if ($verification && in_array($verification->status, ['queued', 'running'], true)) {
                $verification->update(['status' => 'failed', 'error' => 'Queue execution failed. Retry verification.', 'checked_at' => now()]);
                $verification->backlink->update(['status' => 'failed']);
            }
        });
    }
}
