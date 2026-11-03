<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Keyword extends TenantModel
{
    protected $casts = ['project_id' => 'integer'];

    use HasFactory;

    protected $guarded = ['id', 'agency_id'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RankingEntry::class);
    }
}
