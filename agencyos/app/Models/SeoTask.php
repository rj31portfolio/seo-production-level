<?php

namespace App\Models;

use Database\Factories\SeoTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoTask extends TenantModel
{
    /** @use HasFactory<SeoTaskFactory> */
    use HasFactory;

    protected $guarded = ['id', 'agency_id'];

    protected $casts = ['project_id' => 'integer', 'client_id' => 'integer', 'website_id' => 'integer', 'seo_tool_run_id' => 'integer', 'assigned_to' => 'integer', 'created_by' => 'integer', 'due_at' => 'datetime', 'completed_at' => 'datetime'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
