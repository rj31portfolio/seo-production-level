<?php

namespace App\Models;

use Database\Factories\SeoToolRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SeoToolRun extends TenantModel
{
    /** @use HasFactory<SeoToolRunFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = ['id', 'agency_id'];

    protected function casts(): array
    {
        return ['input' => 'array', 'summary' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function results(): HasMany
    {
        return $this->hasMany(SeoToolResult::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
