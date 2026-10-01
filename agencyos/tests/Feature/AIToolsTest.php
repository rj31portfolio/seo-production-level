<?php

namespace Tests\Feature;

use App\Jobs\ExecuteAITool;
use App\Models\Agency;
use App\Models\Role;
use App\Models\SeoToolRun;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AI\AIService;
use App\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AIToolsTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->withoutVite();
        Http::preventStrayRequests();
        Queue::fake();
        config(['services.deepseek.api_key' => null, 'services.deepseek.enabled' => false]);
        $this->agency = Agency::create(['name' => 'AI agency']);
        $this->user = User::factory()->create();
        $this->agency->users()->attach($this->user, ['role_id' => Role::where('name', 'agency_owner')->value('id')]);
        $this->actingAs($this->user)->withSession(['agency_id' => $this->agency->id]);
    }

    private function configure(): void
    {
        SystemSetting::create(['key' => 'ai_settings', 'value' => ['enabled' => true, 'daily_limit' => 1]]);
        SystemSetting::create(['key' => 'ai_key', 'value' => ['encrypted' => Crypt::encryptString('test-secret')]]);
    }

    public function test_disabled_ai_does_not_consume_quota_and_normal_tools_continue(): void
    {
        $this->get('/seo/tools/content-brief')->assertOk();
        $this->post('/seo/tools/content-brief', ['content' => 'Roof repair guide'])->assertSessionHasErrors('content');
        $this->assertDatabaseCount('seo_tool_runs', 0);
        $this->assertDatabaseCount('ai_requests', 0);
        $this->post('/seo/tools/content-analyzer', ['content' => 'Actual content'])->assertRedirect();
        Http::assertNothingSent();
    }

    public function test_ai_is_queued_limited_and_stores_recommendations_separately_from_measurements(): void
    {
        $this->configure();
        Http::fake(['api.deepseek.com/*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode(['recommendations' => ['Use a clear guide outline.']])]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20, 'total_tokens' => 120]])]);
        $this->post('/seo/tools/content-brief', ['content' => 'Roof repair guide'])->assertRedirect();
        Queue::assertPushed(ExecuteAITool::class);
        $id = DB::table('seo_tool_runs')->value('id');
        app()->call([new ExecuteAITool($this->agency->id, $id), 'handle']);
        $this->assertDatabaseHas('seo_tool_runs', ['id' => $id, 'status' => 'completed', 'source' => 'DeepSeek AI']);
        $this->assertDatabaseHas('ai_requests', ['total_tokens' => 120]);
        $this->get('/seo/runs/'.$id)->assertOk()->assertSee('Use a clear guide outline.');
        $data = json_decode(DB::table('seo_tool_results')->value('data'), true);
        $this->assertArrayNotHasKey('score', $data);
        $this->post('/seo/tools/ai-assistant', ['content' => 'Any suggestions?'])->assertSessionHasErrors('content');
        $this->assertDatabaseCount('ai_requests', 1);
        $this->assertDatabaseCount('seo_tool_runs', 1);
    }

    public function test_provider_failure_preserves_no_api_functionality(): void
    {
        $this->configure();
        Http::fake(['api.deepseek.com/*' => Http::response(['error' => 'provider failure with sensitive details'], 503)]);
        $this->post('/seo/tools/ai-assistant', ['content' => 'Review supplied page'])->assertRedirect();
        $id = DB::table('seo_tool_runs')->value('id');
        app()->call([new ExecuteAITool($this->agency->id, $id), 'handle']);
        $this->assertDatabaseHas('seo_tool_runs', ['id' => $id, 'status' => 'failed']);
        $this->get('/seo/runs/'.$id)->assertOk()->assertSee('Integration unavailable')->assertDontSee('sensitive details');
        $this->post('/seo/tools/content-analyzer', ['content' => 'Offline analysis works'])->assertRedirect();
    }

    public function test_settings_encrypt_keys_and_reject_arbitrary_provider_urls(): void
    {
        $this->get('/super-admin/ai-settings')->assertForbidden();
        $this->user->forceFill(['is_super_admin' => true])->save();
        $settings = AIService::settings() + ['api_key' => 'private-test-key'];
        $this->patch('/super-admin/ai-settings', $settings)->assertRedirect();
        $this->assertStringNotContainsString('private-test-key', json_encode(SystemSetting::find('ai_key')->value));
        $this->get('/super-admin/ai-settings')->assertOk()->assertDontSee('private-test-key');
        $settings['base_url'] = 'http://127.0.0.1';
        $this->patch('/super-admin/ai-settings', $settings)->assertSessionHasErrors('base_url')->assertSessionMissing('_old_input.api_key');
    }

    public function test_environment_configuration_can_execute_ai_without_database_credentials(): void
    {
        config(['services.deepseek.api_key' => 'environment-test-key', 'services.deepseek.enabled' => true, 'services.deepseek.model' => 'deepseek-flash', 'services.deepseek.base_url' => 'https://api.deepseek.com/v1']);
        Http::fake(['api.deepseek.com/*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode(['recommendations' => ['Review the collected evidence.']])]]]])]);
        $this->post('/seo/tools/ai-assistant', ['content' => 'Explain my audit'])->assertRedirect()->assertSessionHasNoErrors();
        $id = DB::table('seo_tool_runs')->value('id');
        app()->call([new ExecuteAITool($this->agency->id, $id), 'handle']);
        $this->assertDatabaseHas('seo_tool_runs', ['id' => $id, 'status' => 'completed']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.deepseek.com/v1/chat/completions' && $request->hasHeader('Authorization', 'Bearer environment-test-key') && $request['model'] === 'deepseek-flash');
        $this->user->forceFill(['is_super_admin' => true])->save();
        $this->get('/super-admin/ai-settings')->assertOk()->assertSee('An environment key is configured')->assertDontSee('environment-test-key');
        $this->assertDatabaseMissing('system_settings', ['key' => 'ai_key']);
    }

    public function test_saved_settings_override_environment_and_invalid_saved_keys_do_not_fall_back(): void
    {
        config(['services.deepseek.api_key' => 'environment-test-key', 'services.deepseek.enabled' => true]);
        SystemSetting::create(['key' => 'ai_settings', 'value' => ['enabled' => false, 'model' => 'saved-model']]);
        SystemSetting::create(['key' => 'ai_key', 'value' => ['encrypted' => Crypt::encryptString('stored-test-key')]]);
        $this->assertSame('stored-test-key', app(AIService::class)->key());
        $this->assertSame('saved-model', AIService::settings()['model']);
        $this->post('/seo/tools/ai-assistant', ['content' => 'Explain my audit'])->assertSessionHasErrors('content');
        $this->assertDatabaseCount('ai_requests', 0);
        SystemSetting::find('ai_key')->update(['value' => ['encrypted' => 'invalid-ciphertext']]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AI key could not be decrypted');
        app(AIService::class)->key();
    }

    public function test_ai_audit_explanation_receives_duplicate_metadata_and_fetch_gaps(): void
    {
        $this->configure();
        $source = app(TenantContext::class)->run($this->agency, function (): SeoToolRun {
            $run = SeoToolRun::create(['user_id' => $this->user->id, 'tool' => 'audit', 'status' => 'completed', 'source' => 'Internal crawler', 'input' => []]);
            for ($page = 0; $page < 30; $page++) {
                $run->results()->create(['kind' => 'page', 'url' => 'https://example.com/page-'.$page, 'data' => ['title' => 'Page '.$page]]);
            }
            $run->results()->create(['kind' => 'crawl_summary', 'data' => ['duplicate_titles' => ['Shared title' => ['https://example.com/a', 'https://example.com/b']]]]);
            $run->results()->create(['kind' => 'fetch_error', 'url' => 'https://example.com/unavailable', 'data' => ['error' => 'Fetch timed out.']]);

            return $run;
        });
        Http::fake(['api.deepseek.com/*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode(['recommendations' => ['Review duplicated titles and missing evidence.']])]]]])]);
        $this->post('/seo/tools/ai-audit-explanation', ['content' => 'Explain collected findings.', 'data_run_id' => $source->id])->assertRedirect()->assertSessionHasNoErrors();
        $id = DB::table('seo_tool_runs')->max('id');
        app()->call([new ExecuteAITool($this->agency->id, $id), 'handle']);
        Http::assertSent(function ($request): bool {
            $evidence = json_decode($request['messages'][1]['content'], true)['collected_evidence'];

            return count($evidence) === 30 && $evidence[0]['duplicate_titles']['Shared title'] === ['https://example.com/a', 'https://example.com/b'] && $evidence[1]['error'] === 'Fetch timed out.';
        });
        $this->assertDatabaseHas('seo_tool_runs', ['id' => $id, 'status' => 'completed']);
    }
}
