<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['agency_id' => 'integer', 'user_id' => 'integer', 'subject_id' => 'integer', 'changes' => 'array', 'created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
