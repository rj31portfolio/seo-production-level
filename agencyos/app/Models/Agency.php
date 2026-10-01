<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agency extends Model
{
    protected $fillable = ['name', 'status', 'timezone', 'currency'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'agency_users')->withPivot('role_id')->withTimestamps();
    }
}
