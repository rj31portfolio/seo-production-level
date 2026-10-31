<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallationTest extends TestCase
{
    use RefreshDatabase;

    public function test_installation_is_repeatable_and_preserves_keys_accounts_and_agencies(): void
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $key = config('app.key');
        $agency = Agency::create(['name' => 'Existing customer']);
        $user = User::factory()->create();
        foreach ([1, 2] as $attempt) {
            $this->artisan('agencyos:install')->expectsOutput('Existing application key preserved.')->assertSuccessful();
        }
        $this->assertSame($key, config('app.key'));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('agencies', ['id' => $agency->id, 'name' => 'Existing customer']);
        $this->assertDatabaseHas('roles', ['name' => 'agency_owner']);
    }

    public function test_installation_refuses_unforced_production_changes(): void
    {
        app()->instance('env', 'production');
        $this->artisan('agencyos:install')->expectsOutput('Use --force to install in production after configuring your private .env and database backup.')->assertFailed();
        $this->assertDatabaseCount('roles', 0);
    }

    public function test_production_doctor_fails_for_development_configuration(): void
    {
        config(['app.debug' => true, 'app.url' => 'http://localhost', 'session.secure' => false, 'mail.default' => 'log', 'queue.default' => 'sync']);
        $this->artisan('agencyos:doctor', ['--production' => true])
            ->expectsOutput('Debug disabled: ERROR')
            ->expectsOutput('HTTPS application URL: ERROR')
            ->expectsOutput('Secure session cookies: ERROR')
            ->expectsOutput('Deliverable mail transport: ERROR')
            ->expectsOutput('Asynchronous queue backend: ERROR')
            ->assertFailed();
    }
}
