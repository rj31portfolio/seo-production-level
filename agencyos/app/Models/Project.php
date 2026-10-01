<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends TenantModel
{
    use SoftDeletes;

    protected $fillable = ['client_id', 'website_id', 'name', 'type', 'start_date', 'target_locations', 'strategy', 'goals', 'notes', 'status'];

    protected function casts(): array
    {
        return ['start_date' => 'date'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function website()
    {
        return $this->belongsTo(Website::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'project_users')->withPivot(['agency_id', 'assignment_role']);
    }
}
