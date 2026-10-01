<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoToolRun extends TenantModel
{
    /** @use HasFactory<\Database\Factories\SeoToolRunFactory> */
    use HasFactory;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $guarded = ['id','agency_id'];

    protected function casts(): array { return ['input'=>'array','summary'=>'array','started_at'=>'datetime','finished_at'=>'datetime']; }
    public function results(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(SeoToolResult::class); }
    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Project::class); }
    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Client::class)->withTrashed(); }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
}
