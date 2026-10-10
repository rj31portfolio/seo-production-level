<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Backlink;
use App\Models\Client;
use App\Models\Project;
use App\Models\Report;
use App\Models\Role;
use App\Models\SeoToolRun;
use App\Models\User;
use App\Models\Website;
use App\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Tests\TestCase;

class ClientPortalTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $owner;

    private User $customer;

    private Client $client;

    private Report $report;

    private Report $otherReport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
        $this->agency = Agency::create(['name' => 'Portal agency']);
        $this->owner = User::factory()->create();
        $this->customer = User::factory()->create(['password' => 'PortalPassword123']);
        $this->agency->users()->attach($this->owner, ['role_id' => Role::where('name', 'agency_owner')->value('id')]);
        $this->agency->users()->attach($this->customer, ['role_id' => Role::where('name', 'client')->value('id')]);
        app(TenantContext::class)->run($this->agency, function (): void {
            $this->client = Client::create(['name' => 'My client']);
            $this->client->portal_user_id = $this->customer->id;
            $this->client->save();
            $this->report = $this->fixture($this->client, 'My report', 'https://my-source.example.com/');
            $other = Client::create(['name' => 'Other client']);
            $this->otherReport = $this->fixture($other, 'Private other report', 'https://private-source.example.com/');
        });
        $this->actingAs($this->customer)->withSession(['agency_id' => $this->agency->id]);
    }

    private function fixture(Client $client, string $title, string $source): Report
    {
        $website = Website::forceCreate(['client_id' => $client->id, 'name' => $title, 'url' => 'https://example.com', 'verification_token' => str_repeat('a', 64)]);
        $project = Project::create(['client_id' => $client->id, 'website_id' => $website->id, 'name' => $title, 'type' => 'seo', 'start_date' => '2026-10-01', 'status' => 'active']);
        $run = SeoToolRun::create(['user_id' => $this->owner->id, 'client_id' => $client->id, 'project_id' => $project->id, 'website_id' => $website->id, 'tool' => 'audit', 'status' => 'completed', 'source' => 'Internal crawler', 'input' => []]);
        Backlink::create(['user_id' => $this->owner->id, 'project_id' => $project->id, 'source_url' => $source, 'target_url' => 'https://example.com/', 'anchor' => '=SUM(1,2)', 'url_pair_hash' => hash('sha256', $source), 'source' => 'Manual']);

        return Report::create(['user_id' => $this->owner->id, 'project_id' => $project->id, 'seo_tool_run_id' => $run->id, 'title' => $title, 'snapshot' => ['agency' => 'Portal agency', 'project' => $title, 'source' => 'Internal crawler', 'collected_at' => '2026-10-01', 'summary' => [], 'rows' => [['url' => 'https://example.com/', 'kind' => 'page', 'metrics' => ['word_count' => 32], 'checks' => [], 'score' => null, 'error' => null, 'recommendations' => [], 'notes' => []]], 'missing_data' => []]]);
    }

    private function workbook(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'portal');
        file_put_contents($path, $content);
        try {
            $book = (new Xlsx)->load($path);
            try {
                return $book->getActiveSheet()->toArray(null, false, false, false);
            } finally {
                $book->disconnectWorksheets();
            }
        } finally {
            unlink($path);
        }
    }

    public function test_client_sees_only_their_reports_and_backlinks_with_safe_excel_exports(): void
    {
        $this->get('/portal/reports')->assertOk()->assertSee('My report')->assertDontSee('Private other report')->assertDontSee('Bulk SEO uploads')->assertDontSee('Team members');
        $this->get('/portal/reports/'.$this->report->id)->assertOk()->assertDontSee('Delete report')->assertSee('Download Excel');
        $this->get('/portal/reports/'.$this->otherReport->id)->assertNotFound();
        $this->get('/portal/reports/'.$this->otherReport->id.'/excel')->assertNotFound();
        $this->get('/portal/backlinks')->assertOk()->assertSee('my-source.example.com')->assertDontSee('private-source.example.com');
        $rows = $this->workbook($this->get('/portal/backlinks/excel')->assertOk()->assertDownload('backlinks.xlsx')->streamedContent());
        $this->assertCount(2, $rows);
        $this->assertSame('=SUM(1,2)', $rows[1][3]);
        $rows = $this->workbook($this->get('/portal/reports/'.$this->report->id.'/excel')->assertOk()->streamedContent());
        $this->assertContains(['https://example.com/', 'Metrics', 'word_count', '32'], $rows);
        app(TenantContext::class)->run($this->agency, fn () => $this->report->run->delete());
        $this->get('/portal/reports')->assertOk()->assertSee('My report');
        foreach (['/seo/tools', '/clients', '/seo/bulk-upload', '/seo/tasks', '/notifications'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->get('/dashboard')->assertRedirect(route('portal.reports.index'));
    }

    public function test_client_pdf_download_is_scoped_and_inactive_logins_are_blocked(): void
    {
        Storage::fake('local');
        app(TenantContext::class)->run($this->agency, fn () => $this->report->update(['pdf_status' => 'completed', 'pdf_path' => 'reports/test.pdf']));
        Storage::disk('local')->put('reports/test.pdf', '%PDF-test');
        $this->get('/portal/reports/'.$this->report->id.'/pdf')->assertOk()->assertDownload();
        $this->get('/portal/reports/'.$this->otherReport->id.'/pdf')->assertNotFound();
        $this->customer->forceFill(['is_active' => false])->save();
        $this->get('/portal/reports')->assertForbidden();
    }

    public function test_owner_can_create_disable_and_reset_a_client_login_without_granting_staff_access(): void
    {
        $this->actingAs($this->owner);
        $client = app(TenantContext::class)->run($this->agency, fn () => Client::create(['name' => 'New portal client']));
        $this->post('/clients/'.$client->id.'/login', ['email' => 'new-client@example.com', 'password' => 'ClientPassword123', 'password_confirmation' => 'ClientPassword123', 'enabled' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $user = User::where('email', 'new-client@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('ClientPassword123', $user->password));
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'portal_user_id' => $user->id]);
        $this->post('/clients/'.$client->id.'/login', ['email' => $user->email, 'enabled' => 0])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->is_active);
        $this->post('/clients/'.$client->id.'/login', ['email' => $user->email, 'enabled' => 1, 'password' => 'UpdatedPassword123', 'password_confirmation' => 'UpdatedPassword123'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('UpdatedPassword123', $user->fresh()->password));
        $this->actingAs($user->fresh());
        $this->get('/portal/reports')->assertOk();
        $this->get('/seo/tools')->assertForbidden();
        $this->post('/clients/'.$this->client->id.'/login', ['email' => 'attack@example.com'])->assertForbidden();
    }

    public function test_login_redirects_clients_to_reports_and_other_agency_sessions_are_rejected(): void
    {
        $this->post('/logout');
        $this->post('/login', ['email' => $this->customer->email, 'password' => 'PortalPassword123'])->assertRedirect(route('portal.reports.index'));
        $other = Agency::create(['name' => 'Other agency']);
        $this->withSession(['agency_id' => $other->id])->get('/portal/reports')->assertForbidden();
        $this->actingAs($this->owner)->withSession(['agency_id' => $this->agency->id]);
        $this->get('/portal/reports')->assertForbidden();
    }
}
