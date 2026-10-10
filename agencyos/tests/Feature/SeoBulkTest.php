<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Project;
use App\Models\Role;
use App\Models\SeoTask;
use App\Models\SeoToolRun;
use App\Models\User;
use App\Models\Website;
use App\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SeoBulkTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $manager;

    private User $executive;

    private Project $project;

    private SeoTask $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
        $this->agency = Agency::create(['name' => 'Bulk agency']);
        $this->manager = User::factory()->create();
        $this->executive = User::factory()->create();
        $this->agency->users()->attach($this->manager, ['role_id' => Role::where('name', 'seo_manager')->value('id')]);
        $this->agency->users()->attach($this->executive, ['role_id' => Role::where('name', 'seo_executive')->value('id')]);
        app(TenantContext::class)->run($this->agency, function (): void {
            $client = Client::create(['name' => 'Bulk client']);
            $website = Website::forceCreate(['client_id' => $client->id, 'name' => 'Website', 'url' => 'https://example.com', 'verification_token' => str_repeat('a', 64)]);
            $this->project = Project::create(['client_id' => $client->id, 'website_id' => $website->id, 'name' => 'Bulk project', 'type' => 'seo', 'start_date' => '2026-10-01', 'status' => 'active']);
            $run = SeoToolRun::create(['user_id' => $this->manager->id, 'client_id' => $client->id, 'project_id' => $this->project->id, 'website_id' => $website->id, 'tool' => 'audit', 'status' => 'completed', 'source' => 'Internal crawler', 'input' => []]);
            $this->task = SeoTask::factory()->create(['project_id' => $this->project->id, 'client_id' => $client->id, 'website_id' => $website->id, 'seo_tool_run_id' => $run->id, 'created_by' => $this->manager->id]);
        });
        $this->actingAs($this->manager)->withSession(['agency_id' => $this->agency->id]);
    }

    public function test_managers_can_download_templates_and_import_task_progress(): void
    {
        $this->get('/seo/bulk-upload')->assertOk()->assertSee('Backlinks')->assertSee('Keyword ranking observations')->assertSee('Task work progress');
        foreach (['backlinks', 'rankings', 'tasks'] as $type) {
            $this->get('/seo/bulk-upload/templates/'.$type)->assertOk()->assertDownload($type.'-template.xlsx');
        }
        $this->get('/seo/bulk-upload/tasks?project_id='.$this->project->id)->assertOk()->assertDownload('task-progress-'.$this->project->id.'.xlsx');
        $file = UploadedFile::fake()->createWithContent('progress.csv', "task_id,status,completion_note\n".$this->task->id.',completed,Implemented page changes');
        $this->post('/seo/bulk-upload/tasks', ['project_id' => $this->project->id, 'file' => $file])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seo_tasks', ['id' => $this->task->id, 'status' => 'completed', 'completion_note' => 'Implemented page changes']);
        $this->assertNotNull(app(TenantContext::class)->run($this->agency, fn () => $this->task->fresh()->completed_at));
        $this->assertDatabaseHas('activity_logs', ['action' => 'seo_task.progress_imported', 'subject_id' => $this->task->id]);
    }

    public function test_invalid_or_foreign_task_rows_rollback_every_update(): void
    {
        foreach (["task_id,status\n".$this->task->id.",completed\n999999,review", "task_id,status\n".$this->task->id.",completed\n".$this->task->id.',review', "task_id,status\n".$this->task->id.',invalid'] as $csv) {
            $this->post('/seo/bulk-upload/tasks', ['project_id' => $this->project->id, 'file' => UploadedFile::fake()->createWithContent('progress.csv', $csv)])->assertSessionHasErrors('file');
            $this->assertDatabaseHas('seo_tasks', ['id' => $this->task->id, 'status' => 'pending']);
        }
        $otherProject = app(TenantContext::class)->run($this->agency, fn () => Project::create(['client_id' => $this->project->client_id, 'website_id' => $this->project->website_id, 'name' => 'Other project', 'type' => 'seo', 'start_date' => '2026-10-01', 'status' => 'active']));
        $this->post('/seo/bulk-upload/tasks', ['project_id' => $otherProject->id, 'file' => UploadedFile::fake()->createWithContent('progress.csv', "task_id,status\n".$this->task->id.',completed')])->assertSessionHasErrors('file');
        $this->assertDatabaseHas('seo_tasks', ['id' => $this->task->id, 'status' => 'pending']);
        $this->actingAs($this->executive);
        $this->post('/seo/bulk-upload/tasks', ['project_id' => $this->project->id])->assertForbidden();
        $this->get('/seo/bulk-upload/tasks?project_id='.$this->project->id)->assertForbidden();
    }

    public function test_xlsx_progress_import_supports_round_trip_headers_and_reopening(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['task_id', 'title', 'status', 'completion_note'], [$this->task->id, $this->task->title, 'completed', 'Done']]);
        $path = tempnam(sys_get_temp_dir(), 'progress');
        (new Xlsx($book))->save($path);
        try {
            $this->post('/seo/bulk-upload/tasks', ['project_id' => $this->project->id, 'file' => new UploadedFile($path, 'progress.xlsx', null, null, true)])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('seo_tasks', ['id' => $this->task->id, 'status' => 'completed']);
        } finally {
            unlink($path);
            $book->disconnectWorksheets();
        }
        $this->post('/seo/bulk-upload/tasks', ['project_id' => $this->project->id, 'file' => UploadedFile::fake()->createWithContent('progress.csv', "task_id,status,completion_note\n".$this->task->id.',in_progress,Reopened')])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seo_tasks', ['id' => $this->task->id, 'status' => 'in_progress', 'completed_at' => null]);
    }
}
