<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Project;
use App\Models\Role;
use App\Models\SeoMonitoring;
use App\Models\SeoToolRun;
use App\Models\User;
use App\Models\Website;
use App\Services\Seo\MonitoringService;
use App\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_monitor_is_not_queued_twice_and_changes_notify_owner(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->withoutVite();
        Queue::fake();
        $agency = Agency::create(['name' => 'Monitor agency']);
        $user = User::factory()->create();
        $agency->users()->attach($user, ['role_id' => Role::where('name', 'agency_owner')->value('id')]);
        $project = app(TenantContext::class)->run($agency, function (): Project {
            $client = Client::create(['name' => 'Client']);
            $website = Website::forceCreate(['name' => 'Website', 'client_id' => $client->id, 'url' => 'https://example.com/', 'verification_token' => str_repeat('a', 64)]);

            return Project::create(['name' => 'Project', 'client_id' => $client->id, 'website_id' => $website->id, 'start_date' => now()->toDateString()]);
        });
        $this->actingAs($user)->withSession(['agency_id' => $agency->id]);
        $this->get('/seo/monitoring')->assertOk();
        $this->post('/seo/monitoring', ['project_id' => $project->id, 'interval_minutes' => 1, 'enabled' => 1])->assertSessionHasErrors('interval_minutes');
        $this->post('/seo/monitoring', ['project_id' => $project->id, 'interval_minutes' => 60, 'enabled' => 1])->assertRedirect();
        $this->artisan('agencyos:seo-monitors')->assertSuccessful();
        $this->artisan('agencyos:seo-monitors')->assertSuccessful();
        $this->assertDatabaseCount('seo_tool_runs', 1);
        app(TenantContext::class)->run($agency, function () use ($user): void {
            $monitor = SeoMonitoring::firstOrFail();
            $run = SeoToolRun::firstOrFail();
            $run->results()->create(['kind' => 'page', 'url' => 'https://example.com/', 'data' => ['title' => 'Old title']]);
            $run->update(['status' => 'completed']);
            app(MonitoringService::class)->completed($run);
            $this->assertSame($run->id, $monitor->fresh()->last_run_id);
            $second = SeoToolRun::create(['user_id' => $user->id, 'project_id' => $run->project_id, 'tool' => 'page-score', 'source' => 'Internal crawler', 'status' => 'completed', 'input' => ['url' => 'https://example.com/']]);
            $second->results()->create(['kind' => 'page', 'url' => 'https://example.com/', 'data' => ['title' => 'New title']]);
            $monitor->update(['pending_run_id' => $second->id]);
            app(MonitoringService::class)->completed($second);
        });
        $this->assertDatabaseHas('in_app_notifications', ['user_id' => $user->id, 'title' => 'Website changes detected']);
    }
}
