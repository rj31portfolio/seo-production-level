<?php

namespace App\Models;

class ClientSubscription extends TenantModel
{
    protected $fillable = ['client_id', 'client_plan_id', 'starts_at', 'expires_at', 'grace_days', 'expiry_mode', 'status', 'auto_renew', 'notes'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'expires_at' => 'datetime', 'auto_renew' => 'boolean'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function plan()
    {
        return $this->belongsTo(ClientPlan::class, 'client_plan_id');
    }

    public function invoices()
    {
        return $this->hasMany(ClientInvoice::class);
    }

    public function history()
    {
        return $this->hasMany(ClientSubscriptionHistory::class)->latest('id');
    }

    public function remainingDays(): int
    {
        return (int) ceil(now()->diffInSeconds($this->expires_at, false) / 86400);
    }

    public function effectiveStatus(): string
    {
        if (in_array($this->status, ['suspended', 'cancelled'])) {
            return $this->status;
        }
        if ($this->starts_at->isFuture()) {
            return 'scheduled';
        }
        $days = $this->remainingDays();
        $rules = SystemSetting::expiryRules();
        if ($this->expires_at->lte(now())) {
            return $this->expiry_mode === 'grace' && $this->expires_at->copy()->addDays($this->grace_days)->gt(now()) ? 'grace' : 'expired';
        }
        if ($days <= $rules['urgent_days']) {
            return 'urgent';
        }
        if ($days <= $rules['expiring_days']) {
            return 'expiring';
        }
        if ($days <= $rules['renewal_days']) {
            return 'renewal_soon';
        }

        return 'active';
    }

    public function operational(): bool
    {
        return in_array($this->effectiveStatus(), ['active', 'urgent', 'expiring', 'renewal_soon', 'grace']);
    }

    public function progress(): int
    {
        $total = $this->starts_at->diffInSeconds($this->expires_at);

        return $total > 0 ? (int) max(0, min(100, round($this->starts_at->diffInSeconds(now(), false) / $total * 100))) : 100;
    }
}
