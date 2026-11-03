<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaasToolPlan extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['max_pages' => 'integer', 'monthly_pages' => 'integer', 'ai_daily_limit' => 'integer', 'ai_monthly_limit' => 'integer', 'tools' => 'array', 'limits' => 'array'];
}
