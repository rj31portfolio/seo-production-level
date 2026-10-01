<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Role;
use App\Models\SaasToolPlan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ToolPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_features_run_quotas_and_crawl_reservations_are_enforced(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->withoutVite();
        Queue::fake();
        $agency = Agency::create(['name' => 'Plan agency']);
        $user = User::factory()->create();
        $agency->users()->attach($user, ['role_id' => Role::where('name', 'agency_owner')->value('id')]);
        $this->actingAs($user)->withSession(['agency_id' => $agency->id]);
        $plan = SaasToolPlan::create(['name' => 'Limited', 'tools' => ['audit', 'content-analyzer'], 'limits' => ['content-analyzer' => 1], 'max_pages' => 2, 'monthly_pages' => 2]);
        DB::table('agency_tool_plan')->insert(['agency_id' => $agency->id, 'saas_tool_plan_id' => $plan->id]);
        $this->post('/seo/tools/url-analyzer', ['url' => 'https://example.com'])->assertForbidden();
        $this->post('/seo/tools/content-analyzer', ['content' => 'Actual content'])->assertRedirect();
        $this->post('/seo/tools/content-analyzer', ['content' => 'Second run'])->assertSessionHasErrors('tool');
        $this->post('/seo/tools/audit', ['url' => 'https://example.com'])->assertRedirect();
        $input = json_decode(DB::table('seo_tool_runs')->where('tool', 'audit')->value('input'), true);
        $this->assertSame(2, $input['max_pages']);
        $this->post('/seo/tools/audit', ['url' => 'https://example.com'])->assertSessionHasErrors('tool');
        $this->assertSame(2, (int) DB::table('seo_tool_usage')->sum('pages'));
    }
}
