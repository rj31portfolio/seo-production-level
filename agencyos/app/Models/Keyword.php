<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Keyword extends TenantModel
{
    use HasFactory;
    protected $guarded=['id','agency_id'];
    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Project::class); }
    public function entries(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(RankingEntry::class); }
}
