<?php

namespace App\Models;

class RenewalReminder extends TenantModel
{
    protected $casts = ['client_subscription_id' => 'integer', 'days_before' => 'integer'];

    public $timestamps = false;

    protected $guarded = ['id', 'agency_id'];
}
