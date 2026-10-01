<?php

namespace App\Models;

class ClientPayment extends TenantModel
{
    protected $guarded = ['id', 'agency_id'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    public function invoice()
    {
        return $this->belongsTo(ClientInvoice::class, 'client_invoice_id');
    }
}
