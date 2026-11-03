<?php

namespace App\Models;

class ClientPlan extends TenantModel
{
    protected $fillable = ['name', 'price_minor', 'currency', 'duration_days', 'billing_cycle', 'limits', 'is_active'];

    protected $casts = ['duration_days' => 'integer', 'limits' => 'array', 'is_active' => 'boolean', 'price_minor' => 'integer'];

    public function subscriptions()
    {
        return $this->hasMany(ClientSubscription::class);
    }
}
