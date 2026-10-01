<?php

namespace App\Policies;

use App\Models\SeoToolRun;
use App\Models\User;
use App\Services\Seo\ToolRegistry;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

class SeoToolRunPolicy
{
    public function view(User $user, SeoToolRun $run): bool
    {
        if ($run->agency_id !== app(TenantContext::class)->id() || ! $user->hasPermission(ToolRegistry::get($run->tool)['permission'])) {
            return false;
        }

        return $run->project_id ? ($run->project && Gate::forUser($user)->allows('view', $run->project)) : $user->hasPermission('clients.view');
    }

    public function delete(User $user, SeoToolRun $run): bool
    {
        return $this->view($user, $run) && $user->hasPermission('seo_tools.delete') && ! in_array($run->status, ['queued', 'running']);
    }
}
