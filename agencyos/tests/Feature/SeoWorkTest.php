<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportPdf;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Project;
use App\Models\Role;
use App\Models\SeoToolRun;
use App\Models\User;
use App\Models\Website;
use App\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeoWorkTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $owner;

    private User $developer;

    private SeoToolRun $run;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->withoutVite();
        $this->agency = Agency::create(['name' => 'Report agency']);
        $this->owner = User::factory()->create();
        $this->developer = User::factory()->create();
        foreach ([$this->owner->id => 'agency_owner', $this->developer->id => 'developer'] as $id => $role) {
            $this->agency->users()->attach($id, ['role_id' => Role::where('name', $role)->value('id')]);
        }
        $this->run = app(TenantContext::class)->run($this->agency, function (): SeoToolRun {
            $client = Client::create(['name' => 'Client']);
            $website = Website::forceCreate(['client_id' => $client->id, 'name' => 'Example', 'url' => 'https://example.com', 'verification_token' => str_repeat('a', 64)]);
            $project = Project::create(['client_id' => $client->id, 'website_id' => $website->id, 'name' => 'Project', 'type' => 'seo', 'status' => 'active', 'start_date' => '2026-10-01']);
            $project->users()->attach($this->developer, ['agency_id' => $this->agency->id, 'assignment_role' => 'developer']);
            $run = SeoToolRun::create(['user_id' => $this->owner->id, 'client_id' => $client->id, 'project_id' => $project->id, 'website_id' => $website->id, 'tool' => 'audit', 'status' => 'completed', 'source' => 'Internal crawler', 'input' => ['url' => 'https://example.com'], 'summary' => ['analyzed_pages' => 1]]);
            $run->results()->create(['url' => 'https://example.com/', 'kind' => 'page', 'data' => ['metrics' => ['word_count' => 32], 'checks' => [['rule' => 'https', 'passed' => false, 'severity' => 'high', 'category' => 'technical', 'recommendation' => 'Use HTTPS <script>alert(1)</script>'], ['rule' => 'no_noindex', 'passed' => false, 'severity' => 'information', 'category' => 'indexability', 'recommendation' => 'Review intentional exclusion.']]]]);

            return $run;
        });
        $this->actingAs($this->owner)->withSession(['agency_id' => $this->agency->id]);
    }

    public function test_task_generation_is_deduplicated_and_employee_completion_requires_manager_review(): void
    {
        $this->post('/seo/runs/'.$this->run->id.'/tasks')->assertRedirect();
        $this->post('/seo/runs/'.$this->run->id.'/tasks')->assertRedirect();
        $this->assertDatabaseCount('seo_tasks', 1);
        $task = DB::table('seo_tasks')->first();
        $this->assertSame($this->developer->id, $task->assigned_to);
        $this->actingAs($this->developer);
        $this->get('/seo/tasks')->assertOk()->assertSee('Https');
        $this->patch('/seo/tasks/'.$task->id, ['status' => 'completed'])->assertForbidden();
        $this->patch('/seo/tasks/'.$task->id, ['status' => 'review', 'completion_note' => 'Implemented TLS'])->assertRedirect();
        $this->actingAs($this->owner);
        $this->patch('/seo/tasks/'.$task->id, ['status' => 'completed'])->assertRedirect();
        $this->assertDatabaseHas('seo_tasks', ['id' => $task->id, 'status' => 'completed']);
    }

    public function test_reports_are_snapshots_with_escaped_html_and_actual_pdf_export(): void
    {
        $this->post('/seo/runs/'.$this->run->id.'/report')->assertRedirect();
        $id = DB::table('reports')->value('id');
        $this->get('/seo/reports')->assertOk();
        $this->get('/seo/reports/'.$id)->assertOk()->assertSee('32');
        $html = $this->get('/seo/reports/'.$id.'/html')->assertOk()->assertDownload('seo-report-'.$id.'.html');
        $html->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        Queue::fake();
        Storage::fake('local');
        $this->get('/seo/reports/'.$id.'/pdf')->assertStatus(409);
        $this->post('/seo/reports/'.$id.'/pdf')->assertRedirect();
        Queue::assertPushed(GenerateReportPdf::class);
        app()->call([new GenerateReportPdf($this->agency->id, $id), 'handle']);
        $pdf = $this->get('/seo/reports/'.$id.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', file_get_contents($pdf->baseResponse->getFile()->getPathname()));
        app(TenantContext::class)->run($this->agency, fn () => $this->run->results()->first()->update(['data' => ['metrics' => ['word_count' => 999]]]));
        $this->get('/seo/reports/'.$id)->assertSee('32')->assertDontSee('999');
        $other = Agency::create(['name' => 'Other']);
        $other->users()->attach($this->owner, ['role_id' => Role::where('name', 'agency_owner')->value('id')]);
        $this->withSession(['agency_id' => $other->id]);
        $this->get('/seo/reports/'.$id)->assertNotFound();
        $this->get('/seo/reports/'.$id.'/pdf')->assertNotFound();
        $this->post('/seo/runs/'.$this->run->id.'/tasks')->assertNotFound();
    }

    public function test_report_prioritizes_grouped_findings_retains_duplicates_and_excludes_unavailable_scores(): void
    {
        app(TenantContext::class)->run($this->agency, function (): void {
            $first = $this->run->results()->first();
            $first->update(['data' => $first->data + ['title' => 'Shared title', 'score' => ['overall' => 60, 'categories' => ['technical' => 50], 'weights' => ['technical' => 20]]]]);
            $this->run->results()->create(['url' => 'https://example.com/about', 'kind' => 'page', 'data' => ['title' => 'Shared title', 'score' => ['overall' => 80], 'checks' => [
                ['rule' => 'https', 'passed' => false, 'severity' => 'high', 'category' => 'technical', 'recommendation' => 'Use HTTPS'],
                ['rule' => 'http_success', 'passed' => true, 'severity' => 'critical', 'category' => 'technical', 'recommendation' => 'Review status'],
            ]]]);
            $this->run->results()->create(['url' => 'https://example.com/unavailable', 'kind' => 'fetch_error', 'data' => ['error' => 'Fetch timed out.']]);
            $this->run->results()->create(['kind' => 'crawl_summary', 'data' => ['duplicate_titles' => ['Shared title' => ['https://example.com/', 'https://example.com/about']]]]);
        });
        $this->post('/seo/runs/'.$this->run->id.'/report')->assertRedirect();
        $snapshot = json_decode(DB::table('reports')->value('snapshot'), true);
        $analysis = $snapshot['analysis'];
        $this->assertSame(2, $analysis['analyzed_pages']);
        $this->assertSame(1, $analysis['fetch_failures']);
        $this->assertEquals(70, $analysis['average_score']);
        $this->assertSame(4, $analysis['actionable_findings']);
        $this->assertSame(2, $analysis['review_findings']);
        $this->assertSame('https', $analysis['issues'][0]['rule']);
        $this->assertSame(2, $analysis['issues'][0]['affected_pages']);
        $this->assertSame('duplicate_titles', $analysis['issues'][1]['rule']);
        $this->assertEquals(33.3, $analysis['categories']['technical']['pass_rate']);
        $id = DB::table('reports')->value('id');
        $this->get('/seo/reports/'.$id)->assertOk()->assertSee('Prioritized action plan')->assertSee('Category health')->assertSee('Page inventory')->assertSee('Shared title')->assertSee('Fetch timed out.');
        Storage::fake('local');
        Queue::fake();
        $this->post('/seo/reports/'.$id.'/pdf')->assertRedirect();
        app()->call([new GenerateReportPdf($this->agency->id, $id), 'handle']);
        $this->get('/seo/reports/'.$id.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_reports_without_page_scores_and_legacy_snapshots_still_render(): void
    {
        app(TenantContext::class)->run($this->agency, fn () => $this->run->results()->delete());
        $this->post('/seo/runs/'.$this->run->id.'/report')->assertRedirect();
        $snapshot = json_decode(DB::table('reports')->value('snapshot'), true);
        $this->assertNull($snapshot['analysis']['average_score']);
        $this->assertSame(0, $snapshot['analysis']['analyzed_pages']);
        unset($snapshot['analysis'], $snapshot['generated_at'], $snapshot['target_url']);
        DB::table('reports')->update(['snapshot' => json_encode($snapshot)]);
        $this->get('/seo/reports/'.DB::table('reports')->value('id'))->assertOk()->assertSee('Executive summary');
    }
}
