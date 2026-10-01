<?php

namespace App\Services;

use App\Models\ClientInvoice;
use App\Models\ClientPayment;
use App\Models\ClientPlan;
use App\Models\ClientSubscription;
use App\Models\ClientSubscriptionHistory;
use App\Models\InAppNotification;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientBilling
{
    private function snapshot(ClientSubscription $s): array
    {
        return $s->only(['client_plan_id', 'starts_at', 'expires_at', 'status', 'grace_days', 'expiry_mode']);
    }

    private function history(ClientSubscription $s, string $action, ?array $before, string $reason): void
    {
        ClientSubscriptionHistory::create(['client_subscription_id' => $s->id, 'changed_by' => auth()->id(), 'action' => $action, 'before' => $before, 'after' => $this->snapshot($s), 'reason' => $reason]);
        Activity::record('client_subscription.'.$action, $s, ['before' => $before, 'after' => $this->snapshot($s), 'reason' => $reason]);
    }

    private function invoice(ClientSubscription $s, ClientPlan $plan, string $description): ClientInvoice
    {
        return ClientInvoice::create(['client_id' => $s->client_id, 'client_subscription_id' => $s->id, 'number' => 'SEO-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)), 'description' => $description.' — '.$plan->name, 'currency' => $plan->currency, 'amount_minor' => $plan->price_minor, 'status' => $plan->price_minor === 0 ? 'paid' : 'unpaid', 'due_date' => now()->toDateString()]);
    }

    public function create(array $data): ClientSubscription
    {
        return DB::transaction(function () use ($data) {
            $plan = ClientPlan::where('is_active', true)->findOrFail($data['client_plan_id']);
            $subscription = ClientSubscription::create($data + ['status' => 'active']);
            $this->invoice($subscription, $plan, 'SEO service');
            $this->history($subscription, 'created', null, 'Initial service subscription');

            return $subscription;
        });
    }

    public function renew(ClientSubscription $subscription, string $reason): ClientSubscription
    {
        return DB::transaction(function () use ($subscription, $reason) {
            $s = ClientSubscription::lockForUpdate()->findOrFail($subscription->id);
            $plan = $s->plan;
            if (! $plan->is_active) {
                throw ValidationException::withMessages(['plan' => 'Choose an active plan before renewal.']);
            }
            $before = $this->snapshot($s);
            $base = $s->expires_at->gt(now()) ? $s->expires_at->copy() : now();
            $s->expires_at = $base->addDays($plan->duration_days);
            $s->status = 'active';
            $s->save();
            $this->invoice($s, $plan, 'SEO service renewal');
            $this->history($s, 'renewed', $before, $reason);
            foreach (app(TenantContext::class)->agency()->users as $user) {
                if ($user->hasPermission('subscriptions.view')) {
                    InAppNotification::create(['user_id' => $user->id, 'title' => 'Client subscription renewed', 'message' => $s->client->name.' now expires on '.$s->expires_at->format('d M Y'), 'url' => '/client-subscriptions/'.$s->id]);
                }
            }

            return $s;
        });
    }

    public function change(ClientSubscription $subscription, string $action, array $data, string $reason): void
    {
        DB::transaction(function () use ($subscription, $action, $data, $reason) {
            $s = ClientSubscription::lockForUpdate()->findOrFail($subscription->id);
            $before = $this->snapshot($s);
            match ($action) {
                'cancel' => $s->status = 'cancelled', 'suspend' => $s->status = 'suspended', 'reactivate' => $s->status = 'active',
                'extend' => $s->expires_at = $s->expires_at->copy()->addDays($data['days']),
                'plan_change' => $s->client_plan_id = ClientPlan::where('is_active', true)->findOrFail($data['client_plan_id'])->id,
            };
            $s->save();
            $this->history($s, $action, $before, $reason);
        });
    }

    public function payment(ClientInvoice $invoice, array $data): void
    {
        DB::transaction(function () use ($invoice, $data) {
            $invoice = ClientInvoice::lockForUpdate()->findOrFail($invoice->id);
            if ($data['amount_minor'] > $invoice->balanceMinor()) {
                throw ValidationException::withMessages(['amount_minor' => 'Payment exceeds the invoice balance.']);
            }
            $payment = ClientPayment::create($data + ['client_invoice_id' => $invoice->id, 'currency' => $invoice->currency, 'recorded_by' => auth()->id()]);
            $invoice->status = $invoice->balanceMinor() === 0 ? 'paid' : 'partially_paid';
            $invoice->save();
            Activity::record('client_payment.recorded', $payment);
        });
    }
}
