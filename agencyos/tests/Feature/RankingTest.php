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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RankingTest extends TestCase
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
        $this->agency = Agency::create(['name' => 'Rankings']);
        $this->user = User::factory()->create();
        $this->agency->users()->attach($this->user, ['role_id' => Role::where('name', 'agency_owner')->value('id')]);
        $this->project = app(TenantContext::class)->run($this->agency, function (): Project {
            $client = Client::create(['name' => 'Client']);
            $site = Website::forceCreate(['client_id' => $client->id, 'name' => 'Website', 'url' => 'https://example.com', 'verification_token' => str_repeat('a', 64)]);

            return Project::create(['name' => 'SEO project', 'client_id' => $client->id, 'website_id' => $site->id, 'start_date' => now()->toDateString()]);
        });
        $this->actingAs($this->user)->withSession(['agency_id' => $this->agency->id]);
    }

    public function test_manual_history_compares_matching_context_and_exports_observations(): void
    {
        $this->post('/seo/rankings', ['project_id' => $this->project->id, 'keyword' => 'Roof repair'])->assertRedirect();
        $id = DB::table('keywords')->value('id');
        $context = ['country' => 'IN', 'location' => '', 'device' => 'desktop', 'search_engine' => 'google'];
        foreach ([[2, 15], [1, 9]] as [$days,$position]) {
            $this->post('/seo/rankings/'.$id, $context + ['observed_on' => now()->subDays($days)->toDateString(), 'position' => $position])->assertRedirect();
        }
        $this->post('/seo/rankings/'.$id, ['country' => 'IN', 'location' => '', 'device' => 'mobile', 'search_engine' => 'google', 'observed_on' => now()->toDateString(), 'position' => 2])->assertRedirect();
        $this->get('/seo/rankings/'.$id)->assertOk()->assertViewHas('current', 9)->assertViewHas('previous', 15)->assertViewHas('change', 6)->assertViewHas('best', 9)->assertViewHas('worst', 15);
        $this->get('/seo/rankings/export')->assertDownload('ranking-observations.csv');
        $other = Agency::create(['name' => 'Other']);
        $other->users()->attach($this->user, ['role_id' => Role::where('name', 'agency_owner')->value('id')]);
        $this->withSession(['agency_id' => $other->id]);
        $this->get('/seo/rankings/'.$id)->assertNotFound();
        $this->get('/seo/rankings')->assertViewHas('keywords', fn ($q) => $q->total() === 0);
    }

    public function test_import_is_atomic_and_labels_source_without_inventing_positions(): void
    {
        $csv = "keyword,observed_on,position,country,location,device,search_engine\nroof repair,".now()->toDateString().",7,IN,,desktop,google\nroof tiles,".now()->toDateString().",,IN,,desktop,google\n";
        $this->post('/seo/rankings/import', ['project_id' => $this->project->id, 'file' => UploadedFile::fake()->createWithContent('ranks.csv', $csv)])->assertRedirect();
        $this->assertDatabaseHas('ranking_entries', ['position' => 7, 'source' => 'CSV import']);
        $this->assertDatabaseHas('ranking_entries', ['position' => null, 'source' => 'CSV import']);
        $this->post('/seo/rankings/import', ['project_id' => $this->project->id, 'file' => UploadedFile::fake()->createWithContent('ranks.csv', $csv)])->assertSessionHasErrors();
        $this->assertDatabaseCount('ranking_entries', 2);
        $id = DB::table('keywords')->value('id');
        $this->post('/seo/rankings/'.$id, ['position' => 0])->assertSessionHasErrors(['position', 'observed_on', 'country']);
        $this->agency->users()->updateExistingPivot($this->user->id, ['role_id' => Role::where('name', 'developer')->value('id')]);
        $this->get('/seo/rankings')->assertForbidden();
    }
}
