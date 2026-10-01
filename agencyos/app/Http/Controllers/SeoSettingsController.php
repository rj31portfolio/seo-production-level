<?php
namespace App\Http\Controllers;
use App\Models\{Agency,SystemSetting};
use App\Services\Seo\ToolRegistry;
use Illuminate\Http\{Request,RedirectResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
class SeoSettingsController extends Controller
{
    public function index(): View
    {
        return view('seo.settings',['engine'=>ToolRegistry::settings(),'weights'=>SystemSetting::find('seo_score_weights')?->value ?? ['technical'=>20,'on_page'=>25,'content'=>10,'indexability'=>20,'schema'=>5,'internal_linking'=>10,'images'=>10],'disabled'=>SystemSetting::find('seo_disabled_tools')?->value ?? [],'tools'=>ToolRegistry::all(),'agencies'=>Agency::orderBy('name')->get(['id','name']),'limits'=>DB::table('tool_limits')->join('agencies','agencies.id','=','tool_limits.agency_id')->select('tool_limits.*','agencies.name')->orderBy('name')->paginate(30)]);
    }
    public function update(Request $request): RedirectResponse
    {
        $rules=['engine'=>'required|array:max_pages,max_depth,delay_ms,timeout,max_bytes,max_redirects,runs_per_minute','engine.max_pages'=>'required|integer|min:1|max:100','engine.max_depth'=>'required|integer|min:0|max:5','engine.delay_ms'=>'required|integer|min:1000|max:10000','engine.timeout'=>'required|integer|min:3|max:30','engine.max_bytes'=>'required|integer|min:65536|max:5242880','engine.max_redirects'=>'required|integer|min:0|max:10','engine.runs_per_minute'=>'required|integer|min:1|max:30','weights'=>'required|array:technical,on_page,content,indexability,schema,internal_linking,images','disabled'=>'nullable|array','disabled.*'=>Rule::in(array_keys(ToolRegistry::all()))];
        foreach(['technical','on_page','content','indexability','schema','internal_linking','images'] as $key) { $rules['weights.'.$key]='required|integer|min:0|max:100'; }
        $data=$request->validate($rules);
        if(array_sum($data['weights'])===0) { throw \Illuminate\Validation\ValidationException::withMessages(['weights'=>'At least one score weight must be positive.']); }
        DB::transaction(function () use ($data) {
            foreach(['seo_engine'=>$data['engine'],'seo_score_weights'=>$data['weights'],'seo_disabled_tools'=>array_values($data['disabled'] ?? [])] as $key=>$value) { SystemSetting::updateOrCreate(['key'=>$key],['value'=>$value]); }
        });return back()->with('success','SEO engine settings saved.');
    }
    public function limit(Request $request): RedirectResponse
    {
        $data=$request->validate(['agency_id'=>'required|integer|exists:agencies,id','tool'=>['required',Rule::in(array_keys(ToolRegistry::all()))],'monthly_runs'=>'nullable|integer|min:0|max:1000000','enabled'=>'required|boolean']);
        DB::table('tool_limits')->updateOrInsert(['agency_id'=>$data['agency_id'],'tool'=>$data['tool']],['monthly_runs'=>$data['monthly_runs'] ?? null,'enabled'=>$data['enabled'],'updated_at'=>now(),'created_at'=>now()]);return back()->with('success','Agency tool limit saved.');
    }
}
