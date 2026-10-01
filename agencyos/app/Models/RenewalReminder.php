<?php

namespace App\Models;

class RenewalReminder extends TenantModel
{
    public $timestamps = false;

    protected $guarded = ['id', 'agency_id'];
}
