<?php

namespace Tests\Feature;

use App\Jobs\ExecuteSeoTool;
use App\Models\Agency;
use App\Models\Role;
use App\Models\SeoToolRun;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ComparisonToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_detection_uses_saved_samples_and_rejects_other_tenants(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->withoutVite();
        $agency = Agency::create(['name' => 'Comparisons']);
        $user = User::factory()->create();
        $agency->users()->attach($user, ['role_id' => Role::where('name', 'agency_owner')->value('id')]);
        $this->actingAs($user)->withSession(['agency_id' => $agency->id]);
        $ids = app(TenantContext::class)->run($agency, function () use ($user): array {
            $ids = [];
            foreach (['Old title', 'New title'] as $title) {
                $run = SeoToolRun::create(['user_id' => $user->id, 'tool' => 'audit', 'status' => 'completed', 'source' => 'Internal crawler', 'input' => ['url' => 'https://example.com']]);
                $run->results()->create(['url' => 'https://example.com/', 'kind' => 'page', 'data' => ['title' => $title, 'content_hash' => $title]]);
                $ids[] = $run->id;
            }

            return $ids;
        });
        $this->post('/seo/tools/seo-changes', ['before_run_id' => $ids[0], 'after_run_id' => $ids[1]])->assertRedirect();
        $id = DB::table('seo_tool_runs')->max('id');
        $this->get('/seo/runs/'.$id)->assertOk()->assertSee('Old title')->assertSee('New title');
        Queue::fake();
        $this->post('/seo/tools/competitor-analyzer', ['urls' => "https://example.com\nhttps://competitor.com"])->assertRedirect();
        Queue::assertPushed(ExecuteSeoTool::class);
        $this->post('/seo/tools/content-gap', ['urls' => 'https://example.com'])->assertSessionHasErrors('urls');
        $other = Agency::create(['name' => 'Other']);
        $other->users()->attach($user, ['role_id' => Role::where('name', 'agency_owner')->value('id')]);
        $this->withSession(['agency_id' => $other->id]);
        $this->post('/seo/tools/seo-changes', ['before_run_id' => $ids[0], 'after_run_id' => $ids[1]])->assertNotFound();
    }
}
