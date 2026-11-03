<?php

namespace App\Models;

use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends TenantModel
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    protected $guarded = ['id', 'agency_id'];

    protected $casts = ['user_id' => 'integer', 'project_id' => 'integer', 'seo_tool_run_id' => 'integer', 'pdf_requested_by' => 'integer', 'snapshot' => 'array'];

    public function run(): BelongsTo
    {
        return $this->belongsTo(SeoToolRun::class, 'seo_tool_run_id')->withTrashed();
    }
}
