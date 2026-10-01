<?php
namespace Tests\Feature;
use App\Models\{Agency,SystemSetting,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class SeoSettingsTest extends TestCase
{
    use RefreshDatabase;
    public function test_only_super_admin_can_configure_valid_crawler_limits_and_scores(): void
    {
        $this->withoutVite();$user=User::factory()->create();$this->actingAs($user)->get('/super-admin/seo-settings')->assertForbidden();$user->forceFill(['is_super_admin'=>true])->save();
        $this->get('/super-admin/seo-settings')->assertOk();
        $data=['engine'=>config('seo_tools.defaults'),'weights'=>['technical'=>20,'on_page'=>25,'content'=>10,'indexability'=>20,'schema'=>5,'internal_linking'=>10,'images'=>10],'disabled'=>['audit']];
        $this->patch('/super-admin/seo-settings',$data)->assertRedirect();$this->assertSame(['audit'],SystemSetting::find('seo_disabled_tools')->value);
        $data['engine']['max_pages']=100000;$this->patch('/super-admin/seo-settings',$data)->assertSessionHasErrors('engine.max_pages');$this->assertSame(30,SystemSetting::find('seo_engine')->value['max_pages']);
        $agency=Agency::create(['name'=>'Quota agency']);$this->post('/super-admin/seo-settings/limits',['agency_id'=>$agency->id,'tool'=>'audit','monthly_runs'=>3,'enabled'=>1])->assertRedirect();$this->assertDatabaseHas('tool_limits',['agency_id'=>$agency->id,'tool'=>'audit','monthly_runs'=>3]);
    }
}
