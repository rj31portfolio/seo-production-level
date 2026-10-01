<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\ClientPlan;
use App\Models\ClientSubscription;
use App\Models\InAppNotification;
use App\Models\SystemSetting;
use App\Services\Activity;
use App\Services\ClientBilling;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientSubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:active,suspended,cancelled', 'expiry' => 'nullable|in:upcoming,expired']);
        $subscriptions = ClientSubscription::with(['client', 'plan'])->when($request->q, fn ($q, $term) => $q->whereHas('client', fn ($q) => $q->where('name', 'like', '%'.$term.'%')))->when($request->status, fn ($q, $status) => $q->where('status', $status))->when($request->expiry === 'expired', fn ($q) => $q->where('expires_at', '<=', now()))->when($request->expiry === 'upcoming', fn ($q) => $q->whereBetween('expires_at', [now(), now()->addDays(SystemSetting::expiryRules()['renewal_days'])]))->orderBy('expires_at')->paginate(20)->withQueryString();

        return view('subscriptions.index', compact('subscriptions'));
    }

    public function create()
    {
        return view('subscriptions.form', ['clients' => Client::whereDoesntHave('subscription')->orderBy('name')->limit(500)->get(), 'plans' => ClientPlan::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, ClientBilling $billing)
    {
        $agency = app(TenantContext::class)->id();
        $data = $request->validate(['client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where('agency_id', $agency)->whereNull('deleted_at'), Rule::unique('client_subscriptions', 'client_id')->where('agency_id', $agency)], 'client_plan_id' => ['required', 'integer', Rule::exists('client_plans', 'id')->where('agency_id', $agency)->where('is_active', true)], 'starts_at' => 'required|date', 'expires_at' => 'required|date|after:starts_at', 'grace_days' => 'required|integer|min:0|max:365', 'expiry_mode' => 'required|in:grace,read_only,suspend_operations,full_suspension', 'notes' => 'nullable|string|max:10000']);
        $zone = app(TenantContext::class)->agency()->timezone;
        $data['starts_at'] = Carbon::parse($data['starts_at'], $zone)->utc();
        $data['expires_at'] = Carbon::parse($data['expires_at'], $zone)->utc();
        $s = $billing->create($data);

        return redirect()->route('client-subscriptions.show', $s)->with('success', 'SEO service subscription and invoice created.');
    }

    public function show(ClientSubscription $subscription)
    {
        $subscription->load(['client', 'plan']);
        $history = $subscription->history()->with('user')->paginate(10, ['*'], 'history_page')->withQueryString();
        $invoices = $subscription->invoices()->latest()->paginate(10, ['*'], 'invoice_page')->withQueryString();

        return view('subscriptions.show', compact('subscription', 'history', 'invoices'));
    }

    public function renew(Request $request, ClientSubscription $subscription, ClientBilling $billing)
    {
        $data = $request->validate(['reason' => 'required|string|max:2000']);
        $billing->renew($subscription, $data['reason']);

        return back()->with('success', 'Subscription renewed and a new invoice created. Payment remains outstanding until recorded.');
    }

    public function change(Request $request, ClientSubscription $subscription, ClientBilling $billing)
    {
        $agency = app(TenantContext::class)->id();
        $data = $request->validate(['action' => 'required|in:cancel,suspend,reactivate,extend,plan_change', 'reason' => 'required|string|max:2000', 'days' => 'required_if:action,extend|nullable|integer|min:1|max:3650', 'client_plan_id' => ['required_if:action,plan_change', 'nullable', 'integer', Rule::exists('client_plans', 'id')->where('agency_id', $agency)->where('is_active', true)]]);
        $billing->change($subscription, $data['action'], $data, $data['reason']);

        return back()->with('success', 'Subscription updated. History retained.');
    }

    public function plans()
    {
        return view('subscriptions.plans', ['plans' => ClientPlan::orderBy('name')->paginate(20)]);
    }

    public function storePlan(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'price_minor' => 'required|integer|min:0|max:99999999999', 'currency' => 'required|string|size:3|regex:/^[A-Z]{3}$/', 'duration_days' => 'required|integer|min:1|max:3650', 'billing_cycle' => 'required|in:monthly,quarterly,yearly,custom', 'limits' => 'nullable|array', 'limits.*' => 'nullable|integer|min:0']);
        $data['limits'] = array_filter($data['limits'] ?? [], fn ($v) => $v !== null);
        DB::transaction(function () use ($data) {
            $plan = ClientPlan::create($data);
            Activity::record('client_plan.created', $plan);
        });

        return back()->with('success', 'Client SEO plan created.');
    }

    public function togglePlan(ClientPlan $plan)
    {
        DB::transaction(function () use ($plan) {
            $plan->is_active = ! $plan->is_active;
            $plan->save();
            Activity::record('client_plan.status_changed', $plan, ['is_active' => $plan->is_active]);
        });

        return back()->with('success', 'Plan availability updated.');
    }

    public function invoices(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:unpaid,partially_paid,paid']);
        $invoices = ClientInvoice::with('client')->when($request->q, fn ($q, $term) => $q->where('number', 'like', '%'.$term.'%'))->when($request->status, fn ($q, $status) => $q->where('status', $status))->latest()->paginate(20)->withQueryString();

        return view('subscriptions.invoices', compact('invoices'));
    }

    public function invoice(ClientInvoice $invoice)
    {
        $invoice->load(['client', 'payments']);

        return view('subscriptions.invoice', compact('invoice'));
    }

    public function payment(Request $request, ClientInvoice $invoice, ClientBilling $billing)
    {
        $data = $request->validate(['amount_minor' => 'required|integer|min:1|max:99999999999', 'method' => 'required|in:bank_transfer,cash,cheque,other', 'reference' => ['required', 'string', 'max:255', Rule::unique('client_payments', 'reference')->where('agency_id', app(TenantContext::class)->id())], 'paid_at' => 'required|date|before_or_equal:now']);
        $billing->payment($invoice, $data);

        return back()->with('success', 'Payment recorded.');
    }

    public function notifications()
    {
        return view('notifications', ['notifications' => InAppNotification::where('user_id', auth()->id())->latest()->paginate(25)]);
    }

    public function read(InAppNotification $notification)
    {
        abort_unless($notification->user_id === auth()->id(), 403);
        $notification->update(['read_at' => now()]);

        return back()->with('success', 'Notification marked as read.');
    }

    public function expirySettings()
    {
        return view('expiry-settings', ['rules' => SystemSetting::expiryRules()]);
    }

    public function updateExpirySettings(Request $request)
    {
        $data = $request->validate(['renewal_days' => 'required|integer|min:1|max:365', 'expiring_days' => 'required|integer|min:1|lt:renewal_days', 'urgent_days' => 'required|integer|min:0|lt:expiring_days', 'reminders' => 'required|string|max:100']);
        $parts = array_map('trim', explode(',', $data['reminders']));
        foreach ($parts as $part) {
            if (! ctype_digit($part) || (int) $part > 365) {
                throw ValidationException::withMessages(['reminders' => 'Enter comma-separated days from 0 to 365.']);
            }
        }
        $data['reminders'] = array_values(array_unique(array_map('intval', $parts)));
        rsort($data['reminders']);
        DB::transaction(function () use ($data) {
            SystemSetting::updateOrCreate(['key' => 'client_expiry'], ['value' => $data]);
            Activity::record('system.client_expiry_updated', null, $data);
        });

        return back()->with('success', 'Expiry rules saved.');
    }
}
