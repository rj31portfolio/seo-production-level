<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoMonitoring extends TenantModel
{
    use HasFactory;

    protected $table = 'seo_monitoring';

    protected $guarded = ['id', 'agency_id'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'next_due_at' => 'datetime', 'last_checked_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
