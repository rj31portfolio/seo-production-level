<?php

namespace App\Services\Seo;

use App\Models\Agency;
use App\Models\Project;
use App\Models\SaasToolPlan;
use App\Models\SeoToolRun;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ToolAccess
{
    public function plan(): ?SaasToolPlan
    {
        $id = DB::table('agency_tool_plan')->where('agency_id', app(TenantContext::class)->id())->value('saas_tool_plan_id');

        return $id ? SaasToolPlan::find($id) : null;
    }

    public function authorizeRun(User $user, string $tool, ?Project $project): void
    {
        $agency = app(TenantContext::class)->agency();
        abort_unless($user->is_active && $agency?->status === 'active' && $user->agencies()->where('agencies.id', $agency->id)->exists(), 403, 'Active agency membership is required.');
        Gate::authorize(ToolRegistry::get($tool)['permission']);
        abort_unless(ToolRegistry::enabled($tool), 403, 'This tool is disabled by the platform administrator.');
        $plan = $this->plan();
        abort_if($plan && ! in_array($tool, $plan->tools, true), 403, 'Your platform tool plan does not include this feature.');
        if ($project) {
            Gate::authorize('view', $project);
            abort_unless(! $project->client->subscription || $project->client->subscription->operational(), 403, 'Renew this client service before running tools.');
        } else {
            abort_unless($user->hasPermission('clients.view'), 403, 'Select an assigned project to run this tool.');
        }
    }

    public function visibleRuns(User $user): Builder
    {
        $tools = array_keys(array_filter(ToolRegistry::all(), fn ($t) => $user->hasPermission($t['permission'])));
        $query = SeoToolRun::whereIn('tool', $tools);
        if (! $user->hasPermission('clients.view')) {
            $query->where(function (Builder $q) use ($user) {
                $q->whereHas('project.users', fn ($q) => $q->where('users.id', $user->id));
            });
        }

        return $query;
    }

    public function consume(string $tool, int $pages = 0): void
    {
        $agencyId = app(TenantContext::class)->id();
        // Serializing admission on the agency row prevents first-use counter races.
        Agency::whereKey($agencyId)->lockForUpdate()->firstOrFail();
        $limit = DB::table('tool_limits')->where('agency_id', $agencyId)->where('tool', $tool)->first();
        $plan = $this->plan();
        $monthlyLimit = $limit ? $limit->monthly_runs : ($plan?->limits[$tool] ?? null);
        abort_if($limit && ! $limit->enabled, 403, 'This tool is disabled for your agency.');
        $key = ['agency_id' => $agencyId, 'tool' => $tool, 'period' => now()->format('Y-m')];
        $usage = DB::table('seo_tool_usage')->where($key)->first();
        if ($monthlyLimit !== null && ($usage?->runs ?? 0) >= $monthlyLimit) {
            throw ValidationException::withMessages(['tool' => 'Your monthly tool-run limit has been reached.']);
        }
        if ($plan && $plan->monthly_pages !== null && DB::table('seo_tool_usage')->where('agency_id', $agencyId)->where('period', $key['period'])->sum('pages') + $pages > $plan->monthly_pages) {
            throw ValidationException::withMessages(['tool' => 'Your monthly reserved crawl-page limit has been reached.']);
        }
        if ($usage) {
            DB::table('seo_tool_usage')->where('id', $usage->id)->update(['runs' => $usage->runs + 1, 'pages' => $usage->pages + $pages, 'updated_at' => now()]);
        } else {
            DB::table('seo_tool_usage')->insert($key + ['runs' => 1, 'pages' => $pages, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
