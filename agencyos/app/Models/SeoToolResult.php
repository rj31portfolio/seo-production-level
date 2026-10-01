<?php

namespace App\Models;

use Database\Factories\SeoToolResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoToolResult extends TenantModel
{
    /** @use HasFactory<SeoToolResultFactory> */
    use HasFactory;

    protected $guarded = ['id', 'agency_id'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(SeoToolRun::class, 'seo_tool_run_id');
    }
}
