<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AIRequest extends TenantModel
{
    use HasFactory;

    protected $table = 'ai_requests';

    protected $guarded = ['id', 'agency_id'];
}
