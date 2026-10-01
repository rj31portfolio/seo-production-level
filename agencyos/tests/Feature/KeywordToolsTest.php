<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class KeywordToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_and_xlsx_keywords_are_imported_without_executing_formulas(): void
    {
        $file = UploadedFile::fake()->createWithContent('keywords.csv', "keyword\nhow to repair roofs\nbuy roof tiles\n");
        $this->post('/seo/tools/keyword-clustering', ['file' => $file])->assertRedirect();
        $this->assertDatabaseHas('seo_tool_runs', ['source' => 'File import']);
        $book = new Spreadsheet;
        $book->getActiveSheet()->setCellValueExplicit('A1', 'keyword', DataType::TYPE_STRING);
        $book->getActiveSheet()->setCellValueExplicit('A2', 'roof repair', DataType::TYPE_STRING);
        $path = tempnam(sys_get_temp_dir(), 'keywords');
        (new Xlsx($book))->save($path);
        try {
            $upload = new UploadedFile($path, 'keywords.xlsx', null, null, true);
            $this->post('/seo/tools/search-intent', ['file' => $upload])->assertRedirect();
            $id = DB::table('seo_tool_runs')->max('id');
            $response = $this->get('/seo/runs/'.$id.'/xlsx')->assertOk()->assertDownload('seo-run-'.$id.'.xlsx');
            $bytes = $response->streamedContent();
            $this->assertStringStartsWith('PK', $bytes);
        } finally {
            unlink($path);
            $book->disconnectWorksheets();
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->withoutVite();
        $agency = Agency::create(['name' => 'Keywords']);
        $user = User::factory()->create();
        $agency->users()->attach($user, ['role_id' => Role::where('name', 'agency_owner')->value('id')]);
        $this->actingAs($user)->withSession(['agency_id' => $agency->id]);
    }

    public function test_rule_tools_persist_real_input_and_label_unavailable_measurements(): void
    {
        foreach (['keyword-clustering', 'search-intent', 'question-keywords', 'longtail-keywords', 'local-keywords'] as $tool) {
            $this->get('/seo/tools/'.$tool)->assertOk();
            $this->post('/seo/tools/'.$tool, ['keywords' => "how to repair roofs\nbuy roof tiles\nroof repair near me", 'location' => 'Delhi'])->assertRedirect();
            $id = DB::table('seo_tool_runs')->max('id');
            $this->get('/seo/runs/'.$id)->assertOk()->assertSee('Search volume, CPC');
            $this->get('/seo/runs/'.$id.'/export')->assertDownload('seo-run-'.$id.'.csv');
        }
        $data = json_decode(DB::table('seo_tool_results')->orderBy('id')->value('data'), true);
        $this->assertCount(3, $data['keywords']);
        $this->assertSame('informational', $data['keywords'][0]['intent']);
        $this->assertSame('transactional', $data['keywords'][1]['intent']);
        $this->assertSame('local', $data['keywords'][2]['intent']);
        $this->assertNull($data['keywords'][0]['search_volume']);
    }

    public function test_keyword_limits_and_validation_do_not_consume_quota_on_failure(): void
    {
        $this->post('/seo/tools/keyword-clustering', [])->assertSessionHasErrors('keywords');
        $this->post('/seo/tools/keyword-clustering', ['keywords' => str_repeat('x', 201)])->assertSessionHasErrors('keywords');
        $this->post('/seo/tools/keyword-clustering', ['keywords' => implode("\n", range(1, 501))])->assertSessionHasErrors('keywords');
        $this->post('/seo/tools/local-keywords', ['keywords' => 'roof repair'])->assertSessionHasErrors('location');
        $this->assertDatabaseCount('seo_tool_runs', 0);
        $this->assertDatabaseCount('seo_tool_usage',0);
    }
}
