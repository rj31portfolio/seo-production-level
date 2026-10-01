<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

class Activity
{
    public static function record(string $action, ?Model $subject = null, array $changes = [], ?int $agencyId = null): void
    {
        ActivityLog::create(['agency_id' => $agencyId ?? app(TenantContext::class)->agency()?->id, 'user_id' => auth()->id(), 'action' => $action, 'subject_type' => $subject ? $subject::class : null, 'subject_id' => $subject?->getKey(), 'changes' => $changes ?: null, 'ip' => app()->runningInConsole() ? null : request()->ip()]);
    }
}
