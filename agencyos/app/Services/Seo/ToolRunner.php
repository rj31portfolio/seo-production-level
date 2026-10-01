<?php

namespace App\Services\Seo;

use App\Jobs\ExecuteAITool;
use App\Jobs\ExecuteSeoTool;
use App\Models\Project;
use App\Models\SeoToolRun;
use App\Services\Activity;
use App\Services\AI\AIService;
use Illuminate\Support\Facades\DB;

class ToolRunner
{
    public function __construct(private ToolAccess $access, private LocalAnalyzer $local, private KeywordEngine $keywords) {}

    public function start(string $tool, array $input, ?Project $project): SeoToolRun
    {
        $this->access->authorizeRun(auth()->user(), $tool, $project);

        return DB::transaction(function () use ($tool, $input, $project) {
            $network = in_array(ToolRegistry::get($tool)['mode'], ['network', 'comparison'], true);
            $crawl = in_array($tool, ['audit', 'meta-auditor', 'broken-links', 'internal-links'], true);
            $cap = min(ToolRegistry::settings()['max_pages'], $this->access->plan()?->max_pages ?? ToolRegistry::settings()['max_pages']);
            $pages = $network ? (isset($input['urls']) ? count($input['urls']) : ($crawl ? min($input['max_pages'] ?? $cap, $cap) : 1)) : 0;
            if ($crawl) {
                $input['max_pages'] = $pages;
            }$this->access->consume($tool, $pages);
            $run = SeoToolRun::create(['user_id' => auth()->id(), 'client_id' => $project?->client_id, 'project_id' => $project?->id, 'website_id' => $project?->website_id, 'tool' => $tool, 'input' => $input, 'source' => $network ? 'Internal crawler' : ($input['input_source'] ?? 'Manual input'), 'status' => $network ? 'queued' : 'running', 'started_at' => $network ? null : now()]);
            if ($network) {
                ExecuteSeoTool::dispatch($run->agency_id, $run->id);
                Activity::record('seo_tool.queued', $run, ['tool' => $tool]);

                return $run;
            }
            if (ToolRegistry::get($tool)['mode'] === 'ai') {
                app(AIService::class)->reserve($run);
                $run->update(['status' => 'queued', 'source' => 'DeepSeek AI', 'started_at' => null]);
                ExecuteAITool::dispatch($run->agency_id, $run->id);
                Activity::record('seo_tool.queued', $run);

                return $run;
            }
            $data = match (ToolRegistry::get($tool)['mode']) {
                'keywords' => $this->keywords->analyze($tool, $input),'changes' => app(ChangeDetector::class)->analyze($input, $project),default => $this->local->analyze($tool, $input)
            };
            $run->results()->create(['url' => $input['url'] ?? null, 'data' => $data]);
            $run->update(['status' => 'completed', 'summary' => $data['metrics'], 'processed' => 1, 'discovered' => 1, 'finished_at' => now()]);
            Activity::record('seo_tool.completed', $run, ['tool' => $tool]);

            return $run;
        });
    }
}
