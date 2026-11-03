<?php

namespace App\Models;

class ClientSubscriptionHistory extends TenantModel
{
    protected $table = 'client_subscription_history';

    public $timestamps = false;

    protected $guarded = ['id', 'agency_id'];

    protected $casts = ['client_subscription_id' => 'integer', 'changed_by' => 'integer', 'before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
