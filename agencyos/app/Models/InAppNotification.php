<?php

namespace App\Models;

class InAppNotification extends TenantModel
{
    protected $guarded = ['id', 'agency_id'];

    protected $casts = ['user_id' => 'integer', 'read_at' => 'datetime'];
}
