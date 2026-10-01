<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Website extends TenantModel
{
    use SoftDeletes;

    protected $fillable = ['client_id', 'name', 'url', 'cms', 'hosting', 'industry', 'country', 'target_locations', 'status'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}
