<?php

namespace App\Models;

class ClientSubscriptionHistory extends TenantModel
{
    protected $table = 'client_subscription_history';

    public $timestamps = false;

    protected $guarded = ['id', 'agency_id'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
