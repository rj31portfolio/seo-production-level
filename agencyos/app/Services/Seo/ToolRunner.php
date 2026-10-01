<?php
namespace App\Services\Seo;
use App\Models\Project;
use App\Models\SeoToolRun;
use App\Services\Activity;
use Illuminate\Support\Facades\DB;

class ToolRunner
{
    public function __construct(private ToolAccess $access,private LocalAnalyzer $local,private KeywordEngine $keywords) {}
    public function start(string $tool,array $input,?Project $project): SeoToolRun
    {
        $this->access->authorizeRun(auth()->user(),$tool,$project);
        return DB::transaction(function () use ($tool,$input,$project) {
            $this->access->consume($tool);
            $network=ToolRegistry::get($tool)['mode']==='network';
            $run=SeoToolRun::create(['user_id'=>auth()->id(),'client_id'=>$project?->client_id,'project_id'=>$project?->id,'website_id'=>$project?->website_id,'tool'=>$tool,'input'=>$input,'source'=>$network ? 'Internal crawler' : ($input['input_source'] ?? 'Manual input'),'status'=>$network ? 'queued' : 'running','started_at'=>$network ? null : now()]);
            if($network) { \App\Jobs\ExecuteSeoTool::dispatch($run->agency_id,$run->id);Activity::record('seo_tool.queued',$run,['tool'=>$tool]);return $run; }
            $data=ToolRegistry::get($tool)['mode']==='keywords' ? $this->keywords->analyze($tool,$input) : $this->local->analyze($tool,$input);
            $run->results()->create(['url'=>$input['url'] ?? null,'data'=>$data]);
            $run->update(['status'=>'completed','summary'=>$data['metrics'],'processed'=>1,'discovered'=>1,'finished_at'=>now()]);
            Activity::record('seo_tool.completed',$run,['tool'=>$tool]);
            return $run;
        });
    }
}
