<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Backlink extends TenantModel
{
    protected $casts = ['project_id' => 'integer', 'user_id' => 'integer'];

    use HasFactory;

    protected $guarded = ['id', 'agency_id'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(BacklinkVerification::class);
    }
}
