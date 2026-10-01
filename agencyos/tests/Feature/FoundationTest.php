<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Role;
use App\Models\TenantModel;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantProbe extends TenantModel
{
    protected $table = 'tenant_probes';

    protected $guarded = ['id'];
}

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->withoutVite();
    }

    private function owner(string $name = 'Agency'): array
    {
        $agency = Agency::create(['name' => $name]);
        $user = User::factory()->create();
        $agency->users()->attach($user, ['role_id' => Role::where('name', 'agency_owner')->firstOrFail()->id]);

        return [$user, $agency];
    }

    public function test_registration_creates_an_isolated_agency_without_privilege_escalation(): void
    {
        $this->post('/register', ['name' => 'Owner', 'agency_name' => 'Agency One', 'email' => 'owner@example.com', 'password' => 'StrongPassword123', 'password_confirmation' => 'StrongPassword123', 'is_super_admin' => true, 'role' => 'super_admin', 'agency_id' => 999])->assertRedirect('/dashboard');
        $user = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertFalse($user->is_super_admin);
        $this->assertTrue(Hash::check('StrongPassword123', $user->password));
        $this->assertSame('agency_owner', Role::find($user->agencies()->first()->pivot->role_id)->name);
        $this->get('/dashboard')->assertOk()->assertSee('Agency One');
        $this->get('/super-admin')->assertForbidden();
    }

    public function test_login_rotates_session_and_logout_revokes_authentication(): void
    {
        [$user,$agency] = $this->owner();
        $user->update(['password' => 'StrongPassword123']);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'StrongPassword123'])->assertRedirect('/dashboard')->assertSessionHas('agency_id', $agency->id);
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_cross_agency_session_and_switch_are_rejected(): void
    {
        [$user,$agency] = $this->owner('One');
        [, $other] = $this->owner('Two');
        $this->actingAs($user)->withSession(['agency_id' => $other->id])->get('/dashboard')->assertForbidden();
        $this->withSession(['agency_id' => $agency->id])->post('/agency/switch', ['agency_id' => $other->id])->assertNotFound();
        $this->get('/dashboard')->assertOk()->assertDontSee('Two');
    }

    public function test_permissions_are_checked_server_side(): void
    {
        [$user,$agency] = $this->owner();
        $agency->users()->updateExistingPivot($user->id, ['role_id' => Role::where('name', 'developer')->firstOrFail()->id]);
        $this->actingAs($user)->withSession(['agency_id' => $agency->id])->patch('/agency/settings', ['name' => 'Hacked', 'timezone' => 'UTC', 'currency' => 'USD'])->assertForbidden();
        $this->assertDatabaseHas('agencies', ['id' => $agency->id, 'name' => 'Agency']);
    }

    public function test_suspended_agencies_and_inactive_users_cannot_operate(): void
    {
        [$user,$agency] = $this->owner();
        $agency->update(['status' => 'suspended']);
        $this->actingAs($user)->withSession(['agency_id' => $agency->id])->get('/dashboard')->assertForbidden();
        $agency->update(['status' => 'active']);
        $user->is_active = false;
        $user->save();
        $this->get('/dashboard')->assertForbidden();
    }

    public function test_super_admin_can_suspend_and_reactivate_with_audit_history(): void
    {
        $admin = User::factory()->create();
        $admin->is_super_admin = true;
        $admin->save();
        [, $agency] = $this->owner();
        $this->actingAs($admin)->get('/super-admin')->assertOk();
        $this->patch('/super-admin/agencies/'.$agency->id, ['status' => 'suspended'])->assertRedirect();
        $this->assertDatabaseHas('agencies', ['id' => $agency->id, 'status' => 'suspended']);
        $this->assertDatabaseHas('activity_logs', ['agency_id' => $agency->id, 'action' => 'agency.status_changed', 'user_id' => $admin->id]);
        $this->patch('/super-admin/agencies/'.$agency->id, ['status' => 'active'])->assertRedirect();
    }

    public function test_password_reset_uses_the_real_notification_and_invalidates_sessions(): void
    {
        Notification::fake();
        [$user] = $this->owner();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('success');
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
            $this->post('/reset-password', ['email' => $user->email, 'token' => $notification->token, 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])->assertRedirect('/login');
            $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
            $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);

            return true;
        });
    }

    public function test_authentication_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'missing@example.com', 'password' => 'wrong'])->assertSessionHasErrors();
        }
        $this->post('/login', ['email' => 'missing@example.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_tenant_scopes_fail_closed_and_reject_foreign_writes(): void
    {
        Schema::create('tenant_probes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained();
            $t->string('name');
            $t->timestamps();
        });
        [, $one] = $this->owner('One');
        [, $two] = $this->owner('Two');
        $context = app(TenantContext::class);
        $context->run($one, fn () => TenantProbe::create(['name' => 'Private One']));
        $context->run($two, fn () => TenantProbe::create(['name' => 'Private Two']));
        $context->run($one, function () {
            $this->assertSame(['Private One'], TenantProbe::pluck('name')->all());
        });
        $this->assertNull($context->agency());
        try {
            TenantProbe::count();
            $this->fail('Missing tenant context was accepted');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('required', $e->getMessage());
        }
        $this->expectException(\LogicException::class);
        $context->run($one, fn () => TenantProbe::create(['name' => 'Forbidden', 'agency_id' => $two->id]));
    }

    public function test_guest_redirect_and_html_escaping(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        [$user,$agency] = $this->owner('<script>alert(1)</script>');
        $this->actingAs($user)->withSession(['agency_id' => $agency->id])->get('/dashboard')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }
}
