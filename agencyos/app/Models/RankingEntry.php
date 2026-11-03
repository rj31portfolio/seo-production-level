<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RankingEntry extends TenantModel
{
    use HasFactory;

    protected $guarded = ['id', 'agency_id'];

    protected $casts = ['keyword_id' => 'integer', 'user_id' => 'integer', 'observed_on' => 'date', 'position' => 'integer'];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
