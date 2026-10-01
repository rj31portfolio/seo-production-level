<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoToolResult extends TenantModel
{
    /** @use HasFactory<\Database\Factories\SeoToolResultFactory> */
    use HasFactory;
    protected $guarded = ['id','agency_id'];
    protected function casts(): array { return ['data'=>'array']; }
    public function run(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(SeoToolRun::class,'seo_tool_run_id'); }
}
