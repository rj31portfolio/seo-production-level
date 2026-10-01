<?php
namespace App\Http\Controllers;
use App\Models\Project;
use App\Models\SeoToolRun;
use App\Services\Activity;
use App\Services\Seo\ToolAccess;
use App\Services\Seo\ToolRegistry;
use App\Services\Seo\ToolRunner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SeoToolsController extends Controller
{
    public function index(Request $request,ToolAccess $access): View
    {
        $request->validate(['q'=>'nullable|string|max:100','category'=>'nullable|string|max:50','status'=>'nullable|in:queued,running,completed,failed']);
        $tools=array_filter(ToolRegistry::all(),fn ($t)=>$request->user()->hasPermission($t['permission']) && (!$request->q || str_contains(mb_strtolower($t['name']),mb_strtolower($request->q))) && (!$request->category || $t['category']===$request->category));
        $runs=$access->visibleRuns($request->user())->with('project')->when($request->status,fn ($q,$status)=>$q->where('status',$status))->latest()->paginate(15)->withQueryString();
        $usedToday=$access->visibleRuns($request->user())->whereDate('created_at',now()->toDateString())->count();
        return view('seo.hub',compact('tools','runs','usedToday'));
    }
    public function form(Request $request,string $tool): View
    {
        $definition=ToolRegistry::get($tool); Gate::authorize($definition['permission']);
        $projects=Project::query()->when(!$request->user()->hasPermission('clients.view'),fn ($q)=>$q->whereHas('users',fn ($q)=>$q->where('users.id',$request->user()->id)))->orderBy('name')->limit(500)->get(['id','name']);
        return view('seo.form',compact('tool','definition','projects'));
    }
    public function run(Request $request,string $tool,ToolRunner $runner): RedirectResponse
    {
        $definition=ToolRegistry::get($tool); Gate::authorize($definition['permission']);
        $rules=['project_id'=>'nullable|integer','keyword'=>'nullable|string|max:200'];
        if($definition['mode']==='network') { $rules['max_pages']='nullable|integer|min:1|max:'.ToolRegistry::settings()['max_pages']; }
        if($definition['mode']==='keywords') { $rules['keywords']='required_without:file|nullable|string|max:100000';$rules['file']='required_without:keywords|nullable|file|extensions:csv,xlsx|max:256';if($tool==='local-keywords') { $rules['location']='required|string|max:100|regex:/^[\p{L}\p{N}\s.,\-]+$/u'; } }
        elseif ($definition['mode']==='text') { $rules['content']='required|string|max:100000'; }
        else { $rules['url']='required|url:http,https|max:2048'; }
        $data=$request->validate($rules);
        if($request->hasFile('file')) { $data['keywords']=app(\App\Services\Seo\KeywordFile::class)->read($request->file('file'));unset($data['file']);$data['input_source']='File import'; }
        if($definition['mode']==='network' && isset($data['url'])) {
            try { $data['url']=app(\App\Services\Seo\PublicUrl::class)->normalize($data['url']); }
            catch(\RuntimeException $e) { throw ValidationException::withMessages(['url'=>$e->getMessage()]); }
        }
        if (isset($data['url']) && (parse_url($data['url'],PHP_URL_USER)!==null || parse_url($data['url'],PHP_URL_PASS)!==null)) { throw ValidationException::withMessages(['url'=>'URLs containing credentials are not accepted.']); }
        $project=empty($data['project_id']) ? null : Project::findOrFail($data['project_id']); unset($data['project_id']);
        $run=$runner->start($tool,$data,$project);
        return redirect()->route('seo.runs.show',$run)->with('success','Tool run saved.');
    }
    public function show(SeoToolRun $run): View
    {
        Gate::authorize('view',$run); $results=$run->results()->orderBy('id')->paginate(10);
        return view('seo.result',['run'=>$run,'results'=>$results,'definition'=>ToolRegistry::get($run->tool)]);
    }
    public function status(SeoToolRun $run): \Illuminate\Http\JsonResponse
    {
        Gate::authorize('view',$run); return response()->json($run->only(['id','status','processed','discovered','error','finished_at']));
    }
    public function export(SeoToolRun $run): StreamedResponse
    {
        Gate::authorize('view',$run);
        return response()->streamDownload(function () use ($run) {
            $file=fopen('php://output','w'); fputcsv($file,['URL','Source','Kind','Result'],',','"','');
            foreach ($run->results()->lazyById(100) as $result) {
                $cells=[$result->url ?? '',$run->source,$result->kind,json_encode($result->data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)];
                fputcsv($file,array_map(fn ($v)=>preg_match('/^[=+\-@\t\r]/',$v) ? "'".$v : $v,$cells),',','"','');
            } fclose($file);
        },'seo-run-'.$run->id.'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
    public function destroy(SeoToolRun $run): RedirectResponse
    {
        Gate::authorize('delete',$run); $run->delete(); Activity::record('seo_tool.archived',$run);
        return redirect()->route('seo.tools.index')->with('success','Tool run archived.');
    }
}
