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

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
