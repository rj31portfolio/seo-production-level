<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AIRequest extends TenantModel
{
    protected $casts = ['seo_tool_run_id' => 'integer', 'user_id' => 'integer', 'project_id' => 'integer', 'prompt_tokens' => 'integer', 'completion_tokens' => 'integer', 'total_tokens' => 'integer'];

    use HasFactory;

    protected $table = 'ai_requests';

    protected $guarded = ['id', 'agency_id'];
}
