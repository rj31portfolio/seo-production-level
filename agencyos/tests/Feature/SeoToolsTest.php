<?php

namespace Tests\Feature;

use App\Jobs\ExecuteSeoTool;
use App\Models\Agency;
use App\Models\Role;
use App\Models\SeoToolRun;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Seo\SafeFetcher;
use App\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\DevCommands;
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
        $csv = $this->get('/seo/runs/'.$id.'/export')->assertOk()->assertDownload('seo-run-'.$id.'.csv')->streamedContent();
        $this->assertStringContainsString('"word_count":5', str_replace('""', '"', $csv));
        $this->post('/seo/tools/url-analyzer', ['url' => 'https://example.com/SEO_page?q=test'])->assertRedirect();
        $this->assertDatabaseCount('seo_tool_runs', 2);
    }

    public function test_development_worker_processes_seo_jobs_using_the_current_php_runtime(): void
    {
        $worker = collect(DevCommands::commands())->firstWhere('name', 'queue');
        $this->assertNotNull($worker);
        $this->assertStringContainsString('--queue=seo,default', $worker['command']);
        $this->assertStringStartsWith('"'.PHP_BINARY.'" artisan', $worker['command']);
    }

    public function test_another_website_starts_a_fresh_audit_and_preserves_previous_results(): void
    {
        Queue::fake();
        $this->get('/seo/tools')->assertOk()->assertSee('Audit another website')->assertSee('id="audit-url"', false);
        $this->get('/seo/tools/audit')->assertOk()->assertSee('Maximum pages to audit')->assertSee('Start website audit');
        $fetcher = \Mockery::mock(SafeFetcher::class);
        $fetcher->shouldReceive('fetch')->twice()->andReturnUsing(function (string $url): array {
            $html = '<html><head><title>'.parse_url($url, PHP_URL_HOST).'</title></head><body><h1>Collected page</h1></body></html>';

            return ['url' => $url, 'status' => 200, 'headers' => ['content-type' => 'text/html'], 'body' => $html, 'bytes' => strlen($html), 'response_ms' => 10, 'redirects' => []];
        });
        $this->app->instance(SafeFetcher::class, $fetcher);
        $runIds = [];
        foreach (['https://example.com/', 'https://another.example.com/'] as $url) {
            $this->post('/seo/tools/audit', ['url' => $url, 'max_pages' => 1])->assertRedirect()->assertSessionHasNoErrors();
            $id = DB::table('seo_tool_runs')->max('id');
            $runIds[] = $id;
            app()->call([new ExecuteSeoTool($this->agency->id, $id), 'handle']);
            $this->assertDatabaseHas('seo_tool_runs', ['id' => $id, 'status' => 'completed']);
            $this->get('/seo/runs/'.$id)->assertOk()->assertSee(parse_url($url, PHP_URL_HOST))->assertSee('Audit another website');
        }
        $this->assertNotSame($runIds[0], $runIds[1]);
        $this->assertDatabaseCount('seo_tool_runs', 2);
        $oldResult = json_decode(DB::table('seo_tool_results')->where('seo_tool_run_id', $runIds[0])->where('kind', 'page')->value('data'), true);
        $this->assertSame('example.com', $oldResult['title']);
        $newResult = DB::table('seo_tool_results')->where('seo_tool_run_id', $runIds[1])->where('kind', 'page')->first();
        $this->assertSame('https://another.example.com/', $newResult->url);
        Queue::assertPushed(ExecuteSeoTool::class, 2);
        $this->get('/seo/tools')->assertOk()->assertSee('Tool history');
        $this->get('/seo/tools/audit')->assertOk()->assertSee('value=""', false)->assertDontSee('another.example.com');
    }

    public function test_quick_audit_respects_project_requirements_and_disabled_tools(): void
    {
        $this->agency->users()->updateExistingPivot($this->owner->id, ['role_id' => Role::where('name', 'developer')->value('id')]);
        $this->get('/seo/tools')->assertOk()->assertSee('Start website audit')->assertDontSee('id="audit-url"', false);
        $this->post('/seo/tools/audit', ['url' => 'https://example.com/'])->assertForbidden();
        $this->agency->users()->updateExistingPivot($this->owner->id, ['role_id' => Role::where('name', 'agency_owner')->value('id')]);
        SystemSetting::create(['key' => 'seo_disabled_tools', 'value' => ['audit']]);
        $this->get('/seo/tools')->assertOk()->assertSee('Website audits are unavailable')->assertDontSee('id="audit-url"', false);
        $this->post('/seo/tools/audit', ['url' => 'https://example.com/'])->assertForbidden();
        $this->assertDatabaseCount('seo_tool_runs', 0);
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
        $data = json_decode(DB::table('seo_tool_results')->value('data'), true);
        $this->assertArrayNotHasKey('score', $data);
    }

    public function test_crawler_stops_when_actual_robots_callback_excludes_the_page(): void
    {
        Queue::fake();
        $this->post('/seo/tools/audit', ['url' => 'https://example.com/'])->assertRedirect();
        $id = DB::table('seo_tool_runs')->value('id');
        $fetcher = \Mockery::mock(SafeFetcher::class);
        $fetcher->shouldReceive('fetch')->andReturnUsing(function (string $url, ?\Closure $before = null): array {
            if ($before) {
                $before($url);
                $this->fail('Excluded page should not be requested.');
            }
            $this->assertSame('https://example.com/robots.txt', $url);

            return ['url' => $url, 'status' => 200, 'headers' => ['content-type' => 'text/plain'], 'body' => "User-agent: *\nDisallow: /", 'bytes' => 30, 'response_ms' => 1, 'redirects' => []];
        });
        $this->app->instance(SafeFetcher::class, $fetcher);
        app()->call([new ExecuteSeoTool($this->agency->id, $id), 'handle']);
        $this->assertDatabaseHas('seo_tool_runs', ['id' => $id, 'status' => 'failed']);
        $data = json_decode(DB::table('seo_tool_results')->value('data'), true);
        $this->assertSame('URL excluded by robots.txt.', $data['error']);
        $this->assertArrayNotHasKey('score', $data);
    }

    public function test_sitemap_collects_bounded_real_xml_entries_and_response_checks(): void
    {
        Queue::fake();
        SystemSetting::create(['key' => 'seo_engine', 'value' => ['delay_ms' => 0, 'max_pages' => 2]]);
        $this->post('/seo/tools/sitemap', ['url' => 'https://example.com/sitemap.xml'])->assertRedirect();
        $id = DB::table('seo_tool_runs')->value('id');
        $fetcher = \Mockery::mock(SafeFetcher::class);
        $fetcher->shouldReceive('fetch')->andReturnUsing(function (string $url, ?\Closure $before = null): array {
            if ($before) {
                $before($url);
            }$body = $url === 'https://example.com/sitemap.xml' ? '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com/a</loc></url><url><loc>https://example.com/b</loc></url><url><loc>https://example.com/a</loc></url></urlset>' : '';

            return ['url' => $url, 'status' => str_ends_with($url, 'robots.txt') || str_ends_with($url, '/b') ? 404 : 200, 'headers' => ['content-type' => 'application/xml'], 'body' => $body, 'bytes' => strlen($body), 'response_ms' => 1, 'redirects' => []];
        });
        $this->app->instance(SafeFetcher::class, $fetcher);
        app()->call([new ExecuteSeoTool($this->agency->id, $id), 'handle']);
        $this->assertDatabaseHas('seo_tool_runs', ['id' => $id, 'status' => 'completed']);
        $data = json_decode(DB::table('seo_tool_results')->where('kind', 'sitemap')->value('data'), true);
        $this->assertSame(2, $data['metrics']['urls']);
        $this->assertSame(1, $data['metrics']['duplicates']);
        $this->assertDatabaseCount('seo_tool_results', 3);
    }
}
