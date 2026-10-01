<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoTask extends TenantModel
{
    /** @use HasFactory<\Database\Factories\SeoTaskFactory> */
    use HasFactory;
    protected $guarded=['id','agency_id'];
    protected function casts(): array { return ['due_at'=>'datetime','completed_at'=>'datetime']; }
    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Project::class); }
    public function assignee(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class,'assigned_to'); }
}
