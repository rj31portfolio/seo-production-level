<?php

namespace App\Models;

class InAppNotification extends TenantModel
{
    protected $guarded = ['id', 'agency_id'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
