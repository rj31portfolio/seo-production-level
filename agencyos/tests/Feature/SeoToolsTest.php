<?php

namespace Tests\Feature;

use App\Jobs\ExecuteSeoTool;
use App\Models\Agency;
use App\Models\Role;
use App\Models\SeoToolRun;
use App\Models\User;
use App\Services\Seo\SafeFetcher;
use App\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SeoToolsTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->withoutVite();
        $this->agency = Agency::create(['name' => 'SEO agency']);
        $this->owner = User::factory()->create();
        $this->agency->users()->attach($this->owner, ['role_id' => Role::where('name', 'agency_owner')->value('id')]);
        $this->actingAs($this->owner)->withSession(['agency_id' => $this->agency->id]);
    }

    public function test_hub_and_local_tools_persist_actual_results_and_export(): void
    {
        $this->get('/seo/tools')->assertOk()->assertSee('Content analyzer');
        $this->get('/seo/tools/content-analyzer')->assertOk();
        $this->post('/seo/tools/content-analyzer', ['content' => 'SEO tools help. SEO matters.', 'keyword' => 'SEO'])->assertRedirect();
        $id = DB::table('seo_tool_runs')->value('id');
        $data = json_decode(DB::table('seo_tool_results')->value('data'), true);
        $this->assertSame(5, $data['metrics']['word_count']);
        $this->assertSame(2, $data['metrics']['keyword_occurrences']);
        $this->get('/seo/runs/'.$id)->assertOk()->assertSee('Manual input');
        $this->get('/seo/runs/'.$id.'/export')->assertOk()->assertDownload('seo-run-'.$id.'.csv');
        $this->post('/seo/tools/url-analyzer', ['url' => 'https://example.com/SEO_page?q=test'])->assertRedirect();
        $this->assertDatabaseCount('seo_tool_runs', 2);
    }

    public function test_invalid_empty_large_input_and_credential_urls_are_rejected(): void
    {
        $this->post('/seo/tools/content-analyzer', [])->assertSessionHasErrors('content');
        $this->post('/seo/tools/content-analyzer', ['content' => str_repeat('x', 100001)])->assertSessionHasErrors('content');
        $this->post('/seo/tools/url-analyzer', ['url' => 'file:///etc/passwd'])->assertSessionHasErrors('url');
        $this->post('/seo/tools/url-analyzer', ['url' => 'https://user:secret@example.com'])->assertSessionHasErrors('url');
        $this->assertDatabaseCount('seo_tool_runs', 0);
    }

    public function test_foreign_agency_results_cannot_be_read_exported_or_archived(): void
    {
        $this->post('/seo/tools/url-analyzer', ['url' => 'https://example.com'])->assertRedirect();
        $id = DB::table('seo_tool_runs')->value('id');
        $other = Agency::create(['name' => 'Other']);
        $user = User::factory()->create();
        $other->users()->attach($user, ['role_id' => Role::where('name', 'agency_owner')->value('id')]);
        $this->actingAs($user)->withSession(['agency_id' => $other->id]);
        $this->get('/seo/runs/'.$id)->assertNotFound();
        $this->get('/seo/runs/'.$id.'/export')->assertNotFound();
        $this->delete('/seo/runs/'.$id)->assertNotFound();
        $this->get('/seo/tools')->assertViewHas('runs', fn ($runs) => $runs->total() === 0);
    }

    public function test_tool_permissions_and_monthly_limits_are_enforced(): void
    {
        DB::table('tool_limits')->insert(['agency_id' => $this->agency->id, 'tool' => 'url-analyzer', 'monthly_runs' => 1, 'enabled' => true]);
        $this->post('/seo/tools/url-analyzer', ['url' => 'https://example.com'])->assertRedirect();
        $this->post('/seo/tools/url-analyzer', ['url' => 'https://example.org'])->assertSessionHasErrors('tool');
        $this->assertDatabaseCount('seo_tool_runs', 1);
        $this->agency->users()->updateExistingPivot($this->owner->id, ['role_id' => Role::where('name', 'developer')->value('id')]);
        $this->get('/seo/tools/content-analyzer')->assertForbidden();
    }

    public function test_network_tool_uses_queue_and_saves_only_collected_measurements(): void
    {
        Queue::fake();
        $this->post('/seo/tools/page-score', ['url' => 'https://example.com'])->assertRedirect();
        Queue::assertPushed(ExecuteSeoTool::class);
        $run = app(TenantContext::class)->run($this->agency, fn () => SeoToolRun::firstOrFail());
        $this->assertSame('queued', $run->status);
        $this->assertDatabaseCount('seo_tool_results', 0);
        $fetcher = \Mockery::mock(SafeFetcher::class);
        $fetcher->shouldReceive('fetch')->once()->andReturn(['url' => 'https://example.com/', 'status' => 200, 'headers' => ['content-type' => 'text/html'], 'body' => '<html><head><title>Real title</title></head><body><h1>Real heading</h1><p>Collected content.</p></body></html>', 'bytes' => 130, 'response_ms' => 10, 'redirects' => []]);
        $this->app->instance(SafeFetcher::class, $fetcher);
        app()->call([new ExecuteSeoTool($this->agency->id, $run->id), 'handle']);
        $this->assertDatabaseHas('seo_tool_runs', ['id' => $run->id, 'status' => 'completed']);
        $this->assertDatabaseCount('seo_tool_results', 1);
    }

    public function test_network_failure_is_saved_without_fabricating_a_score(): void
    {
        Queue::fake();
        $this->post('/seo/tools/page-score', ['url' => 'https://example.com'])->assertRedirect();
        $run = app(TenantContext::class)->run($this->agency, fn () => SeoToolRun::firstOrFail());
        $fetcher = \Mockery::mock(SafeFetcher::class);
        $fetcher->shouldReceive('fetch')->andThrow(new \RuntimeException('Website request timed out.'));
        $this->app->instance(SafeFetcher::class, $fetcher);
        app()->call([new ExecuteSeoTool($this->agency->id, $run->id), 'handle']);
        $this->assertDatabaseHas('seo_tool_runs', ['id' => $run->id, 'status' => 'failed', 'summary' => null]);
        $data = json_decode(DB::table('seo_tool_results')->value('data'),true);
        $this->assertArrayNotHasKey('score',$data);
    }
}
