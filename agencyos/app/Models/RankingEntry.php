<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class RankingEntry extends TenantModel
{
    use HasFactory;
    protected $guarded=['id','agency_id'];
    protected function casts(): array { return ['observed_on'=>'date','position'=>'integer']; }
    public function keyword(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Keyword::class); }
}
