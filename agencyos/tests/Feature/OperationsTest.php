<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\Website;
use App\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->withoutVite();
        $this->agency = Agency::create(['name' => 'One']);
        $this->owner = User::factory()->create();
        $this->agency->users()->attach($this->owner, ['role_id' => Role::where('name', 'agency_owner')->firstOrFail()->id]);
        $this->actingAs($this->owner)->withSession(['agency_id' => $this->agency->id]);
    }

    private function records(?Agency $agency = null): array
    {
        return app(TenantContext::class)->run($agency ?? $this->agency, function () {
            $client = Client::create(['name' => 'Client', 'status' => 'active']);
            $website = new Website(['client_id' => $client->id, 'name' => 'Site', 'url' => 'https://example.com', 'status' => 'active']);
            $website->verification_token = 'test-token';
            $website->save();
            $project = Project::create(['client_id' => $client->id, 'website_id' => $website->id, 'name' => 'Project', 'type' => 'seo', 'start_date' => '2026-10-01', 'status' => 'active']);

            return [$client, $website, $project];
        });
    }

    public function test_client_crud_validation_and_soft_delete(): void
    {
        $this->post('/clients', ['name' => 'Client', 'email' => 'bad', 'status' => 'active'])->assertSessionHasErrors('email');
        $this->post('/clients', ['name' => 'Client', 'email' => 'client@example.com', 'status' => 'active', 'agency_id' => 999])->assertRedirect();
        $id = DB::table('clients')->value('id');
        $this->get('/clients')->assertOk()->assertSee('Client');
        $this->get('/clients/'.$id)->assertOk();
        $this->get('/clients/'.$id.'/edit')->assertOk();
        $this->put('/clients/'.$id, ['name' => 'Updated', 'status' => 'active'])->assertRedirect();
        $this->assertDatabaseHas('clients', ['id' => $id, 'agency_id' => $this->agency->id, 'name' => 'Updated']);
        $this->delete('/clients/'.$id)->assertRedirect('/clients');
        $this->assertSoftDeleted('clients', ['id' => $id]);
    }

    public function test_project_and_website_crud_and_relationship_validation(): void
    {
        [$client,$website,$project] = $this->records();
        $this->get('/projects/create')->assertOk();
        $this->get('/websites/create')->assertOk();
        $this->post('/websites', ['name' => 'New site', 'url' => 'https://example.org', 'client_id' => $client->id, 'status' => 'active'])->assertRedirect();
        $this->post('/projects', ['name' => 'New project', 'client_id' => $client->id, 'website_id' => $website->id, 'start_date' => '2026-10-01', 'type' => 'seo', 'status' => 'active', 'employees' => [$this->owner->id]])->assertRedirect();
        $this->get('/projects/'.$project->id)->assertOk();
        $this->get('/websites/'.$website->id)->assertOk();
        $this->put('/websites/'.$website->id, ['name' => 'Updated site', 'url' => 'https://example.net', 'client_id' => $client->id, 'status' => 'active'])->assertRedirect();
        $this->assertDatabaseHas('websites', ['id' => $website->id, 'url' => 'https://example.net', 'verified_at' => null]);
        $this->delete('/clients/'.$client->id)->assertSessionHasErrors();
        $this->delete('/websites/'.$website->id)->assertSessionHasErrors();
        $this->delete('/projects/'.$project->id)->assertRedirect('/projects');
    }

    public function test_foreign_tenant_ids_are_rejected_for_read_write_and_parent_references(): void
    {
        $other = Agency::create(['name' => 'Other']);
        [$client,$website,$project] = $this->records($other);
        foreach (['clients' => $client, 'websites' => $website, 'projects' => $project] as $module => $record) {
            $this->get('/'.$module.'/'.$record->id)->assertNotFound();
            $this->get('/'.$module.'/'.$record->id.'/edit')->assertNotFound();
            $this->put('/'.$module.'/'.$record->id, [])->assertNotFound();
            $this->delete('/'.$module.'/'.$record->id)->assertNotFound();
        }
        $this->post('/websites', ['client_id' => $client->id, 'name' => 'Attack', 'url' => 'https://example.com', 'status' => 'active'])->assertSessionHasErrors('client_id');
        $this->get('/clients')->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 0);
    }

    public function test_employee_only_sees_assigned_projects_and_websites(): void
    {
        [$client,$website,$project] = $this->records();
        $employee = User::factory()->create();
        $this->agency->users()->attach($employee, ['role_id' => Role::where('name', 'developer')->firstOrFail()->id]);
        $this->actingAs($employee)->withSession(['agency_id' => $this->agency->id]);
        $this->get('/projects/'.$project->id)->assertForbidden();
        $this->get('/websites/'.$website->id)->assertForbidden();
        $this->get('/clients')->assertForbidden();
        app(TenantContext::class)->run($this->agency, fn () => $project->users()->attach($employee, ['agency_id' => $this->agency->id]));
        $this->get('/projects/'.$project->id)->assertOk();
        $this->get('/websites/'.$website->id)->assertOk();
        $this->get('/projects')->assertOk()->assertSee('Project');
        $this->delete('/projects/'.$project->id)->assertForbidden();
    }

    public function test_team_roles_cannot_escalate_or_change_an_owner_and_removal_revokes_access(): void
    {
        $role = Role::where('name', 'developer')->firstOrFail();
        $this->post('/employees', ['name' => 'Developer', 'email' => 'dev@example.com', 'password' => 'StrongPassword123', 'password_confirmation' => 'StrongPassword123', 'role_id' => $role->id])->assertRedirect();
        $employee = User::where('email', 'dev@example.com')->firstOrFail();
        $this->get('/employees')->assertOk();
        $this->patch('/employees/'.$this->owner->id, ['role_id' => $role->id])->assertForbidden();
        $this->patch('/employees/'.$employee->id, ['role_id' => Role::where('name', 'agency_owner')->value('id')])->assertNotFound();
        $this->delete('/employees/'.$employee->id)->assertRedirect();
        $this->actingAs($employee)->get('/dashboard')->assertForbidden();
    }

    public function test_database_composite_foreign_keys_reject_cross_tenant_relationships(): void
    {
        $other = Agency::create(['name' => 'Other']);
        [$client] = $this->records($other);
        $this->expectException(QueryException::class);
        DB::table('websites')->insert(['agency_id' => $this->agency->id, 'client_id' => $client->id, 'name' => 'Attack', 'url' => 'https://example.com', 'verification_token' => 'token']);
    }
}
