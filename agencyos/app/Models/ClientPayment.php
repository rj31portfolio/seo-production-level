<?php

namespace App\Models;

class ClientPayment extends TenantModel
{
    protected $guarded = ['id', 'agency_id'];

    protected $casts = ['client_invoice_id' => 'integer', 'amount_minor' => 'integer', 'recorded_by' => 'integer', 'paid_at' => 'datetime'];

    public function invoice()
    {
        return $this->belongsTo(ClientInvoice::class, 'client_invoice_id');
    }
}
