<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends TenantModel
{
    /** @use HasFactory<\Database\Factories\ReportFactory> */
    use HasFactory;
    protected $guarded=['id','agency_id'];
    protected function casts(): array { return ['snapshot'=>'array']; }
    public function run(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(SeoToolRun::class,'seo_tool_run_id')->withTrashed(); }
}
