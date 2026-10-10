<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends TenantModel
{
    use SoftDeletes;

    protected $fillable = ['name', 'company', 'email', 'phone', 'whatsapp', 'website', 'industry', 'country', 'state', 'city', 'target_locations', 'notes', 'status'];

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'portal_user_id');
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function websites()
    {
        return $this->hasMany(Website::class);
    }

    public function subscription()
    {
        return $this->hasOne(ClientSubscription::class);
    }
}
