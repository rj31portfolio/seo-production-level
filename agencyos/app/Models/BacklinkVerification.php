<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacklinkVerification extends TenantModel
{
    use HasFactory;

    protected $guarded = ['id', 'agency_id'];

    protected $casts = ['backlink_id' => 'integer', 'data' => 'array', 'checked_at' => 'datetime'];

    public function backlink(): BelongsTo
    {
        return $this->belongsTo(Backlink::class);
    }
}
