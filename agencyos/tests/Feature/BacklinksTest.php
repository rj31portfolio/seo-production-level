<?php

namespace Tests\Feature;

use App\Jobs\VerifyBacklink;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\Website;
use App\Services\Seo\NetworkEngine;
use App\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BacklinksTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private Project $project;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->withoutVite();
        $this->agency = Agency::create(['name' => 'Backlinks']);
        $this->user = User::factory()->create();
        $this->agency->users()->attach($this->user, ['role_id' => Role::where('name', 'agency_owner')->value('id')]);
        $this->project = app(TenantContext::class)->run($this->agency, function (): Project {
            $client = Client::create(['name' => 'Client']);
            $site = Website::forceCreate(['client_id' => $client->id, 'name' => 'Website', 'url' => 'https://example.com', 'verification_token' => str_repeat('a', 64)]);

            return Project::create(['name' => 'SEO project', 'client_id' => $client->id, 'website_id' => $site->id, 'start_date' => now()->toDateString()]);
        });
        $this->actingAs($this->user)->withSession(['agency_id' => $this->agency->id]);
    }

    public function test_backlinks_deduplicate_and_verification_records_collected_links(): void
    {
        Queue::fake();
        $data = ['project_id' => $this->project->id, 'source_url' => 'https://source.example.com/', 'target_url' => 'https://example.com/'];
        $this->post('/seo/backlinks', $data)->assertRedirect();
        $this->post('/seo/backlinks', $data)->assertRedirect();
        $this->assertDatabaseCount('backlinks', 1);
        $id = DB::table('backlinks')->value('id');
        $this->get('/seo/backlinks')->assertOk();
        $this->post('/seo/backlinks/'.$id.'/verify')->assertRedirect();
        Queue::assertPushed(VerifyBacklink::class);
        $this->post('/seo/backlinks/'.$id.'/verify')->assertSessionHasErrors('backlink');
        $network = \Mockery::mock(NetworkEngine::class);
        $network->shouldReceive('fetchAllowed')->once()->with($data['source_url'])->andReturn(['url' => $data['source_url'], 'status' => 200, 'headers' => ['content-type' => 'text/html'], 'body' => '<html><body><a href="https://example.com/" rel="nofollow">Roof repair</a></body></html>', 'bytes' => 100, 'response_ms' => 10, 'redirects' => []]);
        $this->app->instance(NetworkEngine::class, $network);
        $verificationId = DB::table('backlink_verifications')->value('id');
        app()->call([new VerifyBacklink($this->agency->id, $verificationId), 'handle']);
        $this->assertDatabaseHas('backlinks', ['id' => $id, 'status' => 'found']);
        $this->get('/seo/backlinks/'.$id)->assertOk()->assertSee('nofollow');
        $this->get('/seo/backlinks/export')->assertDownload('backlinks.csv');
        $other = Agency::create(['name' => 'Other']);
        $other->users()->attach($this->user, ['role_id' => Role::where('name', 'agency_owner')->value('id')]);
        $this->withSession(['agency_id' => $other->id]);
        $this->get('/seo/backlinks/'.$id)->assertNotFound();
        $this->post('/seo/backlinks/'.$id.'/verify')->assertNotFound();
    }

    public function test_restricted_source_is_unverified_and_invalid_credentials_are_rejected(): void
    {
        Queue::fake();
        $this->post('/seo/backlinks', ['project_id' => $this->project->id, 'source_url' => 'https://user:pass@example.com', 'target_url' => 'https://example.com'])->assertSessionHasErrors('source_url');
        $this->post('/seo/backlinks', [])->assertSessionHasErrors();
        $this->post('/seo/backlinks', ['project_id' => $this->project->id, 'source_url' => 'https://source.example.com/', 'target_url' => 'https://example.com/'])->assertRedirect();
        $id = DB::table('backlinks')->value('id');
        $this->post('/seo/backlinks/'.$id.'/verify')->assertRedirect();
        $network = \Mockery::mock(NetworkEngine::class);
        $network->shouldReceive('fetchAllowed')->andThrow(new \RuntimeException('URL excluded by robots.txt.'));
        $this->app->instance(NetworkEngine::class, $network);
        app()->call([new VerifyBacklink($this->agency->id, DB::table('backlink_verifications')->value('id')), 'handle']);
        $this->assertDatabaseHas('backlinks', ['id' => $id, 'status' => 'failed']);
        $this->assertNull(DB::table('backlink_verifications')->value('data'));
    }
}
