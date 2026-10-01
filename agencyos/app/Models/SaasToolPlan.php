<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaasToolPlan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tools' => 'array', 'limits' => 'array'];
    }
}
