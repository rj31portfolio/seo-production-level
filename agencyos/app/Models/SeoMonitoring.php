<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoMonitoring extends TenantModel
{
    use HasFactory;

    protected $table = 'seo_monitoring';

    protected $guarded = ['id', 'agency_id'];

    protected $casts = ['project_id' => 'integer', 'user_id' => 'integer', 'pending_run_id' => 'integer', 'last_run_id' => 'integer', 'interval_minutes' => 'integer', 'enabled' => 'boolean', 'next_due_at' => 'datetime', 'last_checked_at' => 'datetime'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
