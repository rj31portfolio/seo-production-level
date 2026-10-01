<?php

namespace App\Models;

class ClientInvoice extends TenantModel
{
    protected $guarded = ['id', 'agency_id'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'amount_minor' => 'integer'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function subscription()
    {
        return $this->belongsTo(ClientSubscription::class, 'client_subscription_id');
    }

    public function payments()
    {
        return $this->hasMany(ClientPayment::class);
    }

    public function balanceMinor(): int
    {
        return $this->amount_minor - (int) $this->payments()->sum('amount_minor');
    }
}
