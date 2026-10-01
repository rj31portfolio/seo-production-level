<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\SeoToolRun;
use App\Services\Activity;
use App\Services\AI\AIService;
use App\Services\Seo\KeywordFile;
use App\Services\Seo\PublicUrl;
use App\Services\Seo\ToolAccess;
use App\Services\Seo\ToolRegistry;
use App\Services\Seo\ToolRunner;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SeoToolsController extends Controller
{
    public function index(Request $request, ToolAccess $access): View
    {
        $request->validate(['q' => 'nullable|string|max:100', 'category' => 'nullable|string|max:50', 'status' => 'nullable|in:queued,running,completed,failed']);
        $tools = array_filter(ToolRegistry::all(), fn ($t) => $request->user()->hasPermission($t['permission']) && (! $request->q || str_contains(mb_strtolower($t['name']), mb_strtolower($request->q))) && (! $request->category || $t['category'] === $request->category));
        $runs = $access->visibleRuns($request->user())->with('project')->when($request->status, fn ($q, $status) => $q->where('status', $status))->latest()->paginate(15)->withQueryString();
        $usedToday = $access->visibleRuns($request->user())->whereDate('created_at', now()->toDateString())->count();
        $plan = $access->plan();
        $usage = DB::table('seo_tool_usage')->where('agency_id', app(TenantContext::class)->id())->where('period', now()->format('Y-m'))->get()->keyBy('tool');
        $limits = DB::table('tool_limits')->where('agency_id', app(TenantContext::class)->id())->get()->keyBy('tool');
        $queued = $access->visibleRuns($request->user())->whereIn('status', ['queued', 'running'])->count();
        $failed = $access->visibleRuns($request->user())->where('status', 'failed')->count();

        return view('seo.hub', compact('tools', 'runs', 'usedToday', 'plan', 'usage', 'limits', 'queued', 'failed'));
    }

    public function form(Request $request, string $tool): View|RedirectResponse
    {
        $definition = ToolRegistry::get($tool);
        Gate::authorize($definition['permission']);
        if (isset($definition['route'])) {
            return redirect()->route($definition['route']);
        }
        $projects = Project::query()->when(! $request->user()->hasPermission('clients.view'), fn ($q) => $q->whereHas('users', fn ($q) => $q->where('users.id', $request->user()->id)))->orderBy('name')->limit(500)->get(['id', 'name']);
        $contextRuns = in_array(ToolRegistry::get($tool)['mode'], ['ai', 'changes'], true) ? app(ToolAccess::class)->visibleRuns($request->user())->where('status', 'completed')->where('source', 'Internal crawler')->latest()->limit(100)->get(['id', 'tool', 'project_id']) : collect();

        return view('seo.form', compact('tool', 'definition', 'projects', 'contextRuns'));
    }

    public function run(Request $request, string $tool, ToolRunner $runner): RedirectResponse
    {
        $definition = ToolRegistry::get($tool);
        Gate::authorize($definition['permission']);
        abort_if(isset($definition['route']), 404);
        $rules = ['project_id' => 'nullable|integer', 'keyword' => 'nullable|string|max:200'];
        if ($definition['mode'] === 'network') {
            $rules['max_pages'] = 'nullable|integer|min:1|max:'.ToolRegistry::settings()['max_pages'];
        }
        if ($definition['mode'] === 'changes') {
            $rules['before_run_id'] = 'required|integer';
            $rules['after_run_id'] = 'required|integer|different:before_run_id';
        } elseif ($definition['mode'] === 'comparison') {
            $rules['urls'] = 'required|string|max:11000';
        } elseif ($definition['mode'] === 'keywords') {
            $rules['keywords'] = 'required_without:file|nullable|string|max:100000';
            $rules['file'] = 'required_without:keywords|nullable|file|extensions:csv,xlsx|max:256';
            if ($tool === 'local-keywords') {
                $rules['location'] = 'required|string|max:100|regex:/^[\p{L}\p{N}\s.,\-]+$/u';
            }
        } elseif ($definition['mode'] === 'ai') {
            $rules['content'] = 'required|string|max:'.AIService::settings()['max_input_chars'];
            $rules['data_run_id'] = 'nullable|integer';
        } elseif ($definition['mode'] === 'text') {
            $rules['content'] = 'required|string|max:100000';
        } else {
            $rules['url'] = 'required|url:http,https|max:2048';
        }
        $data = $request->validate($rules);
        if ($tool === 'local-seo') {
            $data += $request->validate(['business_name' => 'required|string|max:200', 'location' => 'required|string|max:100', 'address' => 'nullable|string|max:300', 'phone' => 'nullable|string|max:50']);
        }
        if ($definition['mode'] === 'comparison') {
            $urls = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/u', $data['urls'])))));
            if (count($urls) < 2 || count($urls) > 5) {
                throw ValidationException::withMessages(['urls' => 'Supply two to five public HTTP/HTTPS URLs, your URL first.']);
            }
            try {
                $data['urls'] = array_map(fn ($url) => app(PublicUrl::class)->normalize($url), $urls);
                $data['url'] = $data['urls'][0];
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['urls' => $e->getMessage()]);
            }
        }
        if ($request->hasFile('file')) {
            $data['keywords'] = app(KeywordFile::class)->read($request->file('file'));
            unset($data['file']);
            $data['input_source'] = 'File import';
        }
        if ($definition['mode'] === 'network' && isset($data['url'])) {
            try {
                $data['url'] = app(PublicUrl::class)->normalize($data['url']);
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['url' => $e->getMessage()]);
            }
        }
        if (isset($data['url']) && (parse_url($data['url'], PHP_URL_USER) !== null || parse_url($data['url'], PHP_URL_PASS) !== null)) {
            throw ValidationException::withMessages(['url' => 'URLs containing credentials are not accepted.']);
        }
        $project = empty($data['project_id']) ? null : Project::findOrFail($data['project_id']);
        unset($data['project_id']);
        if (! empty($data['data_run_id'])) {
            $contextRun = SeoToolRun::findOrFail($data['data_run_id']);
            Gate::authorize('view', $contextRun);
            abort_unless($contextRun->status === 'completed' && $contextRun->project_id === $project?->id, 422, 'Select a completed run from the same project.');
        }
        $run = $runner->start($tool, $data, $project);

        return redirect()->route('seo.runs.show', $run)->with('success', 'Tool run saved.');
    }

    public function show(SeoToolRun $run): View
    {
        Gate::authorize('view', $run);
        $results = $run->results()->orderBy('id')->paginate(10);

        return view('seo.result', ['run' => $run, 'results' => $results, 'definition' => ToolRegistry::get($run->tool)]);
    }

    public function status(SeoToolRun $run): JsonResponse
    {
        Gate::authorize('view', $run);

        return response()->json($run->only(['id', 'status', 'processed', 'discovered', 'error', 'finished_at']));
    }

    public function export(SeoToolRun $run): StreamedResponse
    {
        Gate::authorize('view', $run);

        return response()->streamDownload(app(TenantContext::class)->wrap(function () use ($run) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['URL', 'Source', 'Kind', 'Result'], ',', '"', '');
            foreach ($run->results()->lazyById(100) as $result) {
                $cells = [$result->url ?? '', $run->source, $result->kind, json_encode($result->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
                fputcsv($file, array_map(fn ($v) => preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v, $cells), ',', '"', '');
            } fclose($file);
        }), 'seo-run-'.$run->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function xlsx(SeoToolRun $run): StreamedResponse
    {
        Gate::authorize('view', $run);

        return response()->streamDownload(app(TenantContext::class)->wrap(function () use ($run): void {
            $book = new Spreadsheet;
            $sheet = $book->getActiveSheet();
            $sheet->setTitle('Collected results');
            $row = 1;
            $write = function (array $cells) use ($sheet, &$row): void {
                foreach (array_values($cells) as $column => $value) {
                    $sheet->setCellValueExplicit([$column + 1, $row], (string) $value, DataType::TYPE_STRING);
                }$row++;
            };
            $write(['URL', 'Source', 'Kind', 'Field', 'Value (JSON segments for large fields)']);
            foreach ($run->results()->lazyById(100) as $result) {
                foreach ($result->data as $field => $value) {
                    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $parts = max(1, (int) ceil(mb_strlen($json) / 30000));
                    for ($i = 0; $i < $parts; $i++) {
                        $write([$result->url ?? '', $run->source, $result->kind, $field.($parts > 1 ? ' [segment '.($i + 1).'/'.$parts.']' : ''), mb_substr($json, $i * 30000, 30000)]);
                    }
                }
            }
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }), 'seo-run-'.$run->id.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function destroy(SeoToolRun $run): RedirectResponse
    {
        Gate::authorize('delete', $run);
        $run->delete();
        Activity::record('seo_tool.archived', $run);

        return redirect()->route('seo.tools.index')->with('success','Tool run archived.');
    }
}
