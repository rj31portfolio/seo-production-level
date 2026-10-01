<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientPlan;
use App\Models\ClientSubscription;
use App\Models\Role;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClientBillingTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $owner;

    private Client $client;

    private ClientPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(6, 0));
        $this->agency = Agency::create(['name' => 'Agency']);
        $this->owner = User::factory()->create();
        $this->agency->users()->attach($this->owner, ['role_id' => Role::where('name', 'agency_owner')->value('id')]);
        app(TenantContext::class)->run($this->agency, function () {
            $this->client = Client::create(['name' => 'Client', 'email' => 'client@example.com', 'status' => 'active']);
            $this->plan = ClientPlan::create(['name' => 'Professional', 'price_minor' => 250000, 'currency' => 'INR', 'duration_days' => 30, 'billing_cycle' => 'monthly']);
        });
        $this->actingAs($this->owner)->withSession(['agency_id' => $this->agency->id]);
    }

    private function subscribe(): ClientSubscription
    {
        $this->post('/client-subscriptions', ['client_id' => $this->client->id, 'client_plan_id' => $this->plan->id, 'starts_at' => '2026-10-01T11:30', 'expires_at' => '2026-10-31T11:30', 'expiry_mode' => 'read_only', 'grace_days' => 0])->assertRedirect();

        return app(TenantContext::class)->run($this->agency, fn () => ClientSubscription::firstOrFail());
    }

    public function test_subscription_creation_generates_real_unpaid_invoice_and_history(): void
    {
        $s = $this->subscribe();
        $this->assertSame('2026-10-31 06:00:00', $s->expires_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('client_invoices', ['agency_id' => $this->agency->id, 'client_subscription_id' => $s->id, 'amount_minor' => 250000, 'status' => 'unpaid']);
        $this->assertDatabaseHas('client_subscription_history', ['client_subscription_id' => $s->id, 'action' => 'created']);
        foreach (['/client-subscriptions', '/client-subscriptions/create', '/client-subscriptions/'.$s->id, '/client-plans', '/invoices', '/notifications'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_renewal_extends_existing_period_and_keeps_history_without_fabricating_payment(): void
    {
        $s = $this->subscribe();
        $this->post('/client-subscriptions/'.$s->id.'/renew', ['reason' => 'Client approved renewal'])->assertRedirect();
        $this->assertDatabaseHas('client_subscriptions', ['id' => $s->id, 'expires_at' => '2026-11-30 06:00:00']);
        $this->assertDatabaseCount('client_invoices', 2);
        $this->assertDatabaseCount('client_payments', 0);
        $this->assertDatabaseCount('client_subscription_history', 2);
        $this->assertDatabaseHas('in_app_notifications', ['user_id' => $this->owner->id, 'title' => 'Client subscription renewed']);
    }

    public function test_payment_balance_duplicate_reference_and_overpayment(): void
    {
        $this->subscribe();
        $invoiceId = DB::table('client_invoices')->value('id');
        $path = '/invoices/'.$invoiceId.'/payments';
        $data = ['amount_minor' => 100000, 'method' => 'bank_transfer', 'reference' => 'bank-001', 'paid_at' => '2026-10-01 05:00'];
        $this->post($path, $data)->assertRedirect();
        $this->assertDatabaseHas('client_invoices', ['id' => $invoiceId, 'status' => 'partially_paid']);
        $this->post($path, $data)->assertSessionHasErrors('reference');
        $this->post($path, array_replace($data, ['amount_minor' => 200000, 'reference' => 'bank-002']))->assertSessionHasErrors('amount_minor');
        $this->post($path, array_replace($data, ['amount_minor' => 150000, 'reference' => 'bank-003']))->assertRedirect();
        $this->assertDatabaseHas('client_invoices', ['id' => $invoiceId, 'status' => 'paid']);
        $this->assertDatabaseCount('client_payments', 2);
        $this->get('/invoices/'.$invoiceId)->assertOk();
    }

    public function test_expiry_grace_boundaries_and_service_access_preserve_records(): void
    {
        $s = $this->subscribe();
        app(TenantContext::class)->run($this->agency, function () use ($s) {
            $this->assertSame('renewal_soon', $s->effectiveStatus());
            $this->travelTo($s->expires_at->copy()->subDays(6));
            $this->assertSame('urgent', $s->effectiveStatus());
            $this->travelTo($s->expires_at);
            $this->assertSame('expired', $s->effectiveStatus());
            $this->assertFalse($s->operational());
            $s->update(['expiry_mode' => 'grace', 'grace_days' => 3]);
            $this->assertSame('grace', $s->effectiveStatus());
            $this->assertTrue($s->operational());
            $this->travelTo($s->expires_at->copy()->addDays(3));
            $this->assertSame('expired', $s->effectiveStatus());
        });
        $this->post('/websites', ['client_id' => $this->client->id, 'name' => 'Blocked', 'url' => 'https://example.com', 'status' => 'active'])->assertSessionHasErrors('client_id');
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('client_subscriptions', 1);
        $this->assertDatabaseCount('client_invoices', 1);
    }

    public function test_reminders_are_idempotent_and_renewal_opens_a_new_reminder_period(): void
    {
        Notification::fake();
        $s = $this->subscribe();
        $this->artisan('agencyos:subscription-reminders')->assertSuccessful();
        $this->artisan('agencyos:subscription-reminders')->assertSuccessful();
        $this->assertDatabaseCount('renewal_reminders', 1);
        $this->assertDatabaseCount('in_app_notifications', 1);
        Notification::assertCount(2);
        $this->post('/client-subscriptions/'.$s->id.'/renew', ['reason' => 'Renew'])->assertRedirect();
        $this->travel(30)->days();
        $this->artisan('agencyos:subscription-reminders')->assertSuccessful();
        $this->assertDatabaseCount('renewal_reminders', 2);
    }

    public function test_changes_require_reason_and_are_audited(): void
    {
        $s = $this->subscribe();
        $path = '/client-subscriptions/'.$s->id;
        $this->patch($path, ['action' => 'suspend'])->assertSessionHasErrors('reason');
        $this->patch($path, ['action' => 'suspend', 'reason' => 'Requested by client'])->assertRedirect();
        $this->assertDatabaseHas('client_subscriptions', ['id' => $s->id, 'status' => 'suspended']);
        $this->patch($path, ['action' => 'reactivate', 'reason' => 'Service restarted'])->assertRedirect();
        $this->patch($path, ['action' => 'extend', 'days' => 5, 'reason' => 'Courtesy extension'])->assertRedirect();
        $this->assertDatabaseCount('client_subscription_history', 4);
    }

    public function test_foreign_subscription_invoice_and_plan_ids_are_inaccessible(): void
    {
        $s = $this->subscribe();
        $invoice = DB::table('client_invoices')->value('id');
        $other = Agency::create(['name' => 'Other']);
        $otherUser = User::factory()->create();
        $other->users()->attach($otherUser, ['role_id' => Role::where('name', 'agency_owner')->value('id')]);
        $this->actingAs($otherUser)->withSession(['agency_id' => $other->id]);
        $this->get('/client-subscriptions/'.$s->id)->assertNotFound();
        $this->get('/invoices/'.$invoice)->assertNotFound();
        $this->patch('/client-plans/'.$this->plan->id)->assertNotFound();
        $this->post('/client-subscriptions/'.$s->id.'/renew', ['reason' => 'Attack'])->assertNotFound();
    }

    public function test_expiry_configuration_is_super_admin_only_and_validated(): void
    {
        $this->get('/super-admin/expiry-settings')->assertForbidden();
        $admin = User::factory()->create();
        $admin->is_super_admin = true;
        $admin->save();
        $this->actingAs($admin);
        $this->get('/super-admin/expiry-settings')->assertOk();
        $this->patch('/super-admin/expiry-settings', ['renewal_days' => 20, 'expiring_days' => 10, 'urgent_days' => 3, 'reminders' => '20,10,3,0'])->assertRedirect();
        $this->assertDatabaseHas('system_settings', ['key' => 'client_expiry']);
        $this->patch('/super-admin/expiry-settings', ['renewal_days' => 20, 'expiring_days' => 21, 'urgent_days' => 3, 'reminders' => 'bad'])->assertSessionHasErrors();
    }
}
