<?php
namespace Tests\Feature;
use App\Models\{Agency,Role,User};
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class KeywordToolsTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();$this->seed(PermissionSeeder::class);$this->withoutVite();$agency=Agency::create(['name'=>'Keywords']);$user=User::factory()->create();$agency->users()->attach($user,['role_id'=>Role::where('name','agency_owner')->value('id')]);$this->actingAs($user)->withSession(['agency_id'=>$agency->id]);
    }
    public function test_rule_tools_persist_real_input_and_label_unavailable_measurements(): void
    {
        foreach(['keyword-clustering','search-intent','question-keywords','longtail-keywords','local-keywords'] as $tool) {
            $this->get('/seo/tools/'.$tool)->assertOk();$this->post('/seo/tools/'.$tool,['keywords'=>"how to repair roofs\nbuy roof tiles\nroof repair near me",'location'=>'Delhi'])->assertRedirect();$id=DB::table('seo_tool_runs')->max('id');$this->get('/seo/runs/'.$id)->assertOk()->assertSee('Search volume, CPC');$this->get('/seo/runs/'.$id.'/export')->assertDownload('seo-run-'.$id.'.csv');
        }
        $data=json_decode(DB::table('seo_tool_results')->orderBy('id')->value('data'),true);$this->assertCount(3,$data['keywords']);$this->assertSame('informational',$data['keywords'][0]['intent']);$this->assertSame('transactional',$data['keywords'][1]['intent']);$this->assertSame('local',$data['keywords'][2]['intent']);$this->assertNull($data['keywords'][0]['search_volume']);
    }
    public function test_keyword_limits_and_validation_do_not_consume_quota_on_failure(): void
    {
        $this->post('/seo/tools/keyword-clustering',[])->assertSessionHasErrors('keywords');$this->post('/seo/tools/keyword-clustering',['keywords'=>str_repeat('x',201)])->assertSessionHasErrors('keywords');
        $this->post('/seo/tools/keyword-clustering',['keywords'=>implode("\n",range(1,501))])->assertSessionHasErrors('keywords');$this->post('/seo/tools/local-keywords',['keywords'=>'roof repair'])->assertSessionHasErrors('location');$this->assertDatabaseCount('seo_tool_runs',0);$this->assertDatabaseCount('seo_tool_usage',0);
    }
}
