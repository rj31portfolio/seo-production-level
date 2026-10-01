<?php

namespace App\Http\Controllers;

use App\Jobs\VerifyBacklink;
use App\Models\Backlink;
use App\Models\Project;
use App\Services\Activity;
use App\Services\Seo\PublicUrl;
use App\Services\Seo\TableFile;
use App\Services\Seo\ToolAccess;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BacklinkController extends Controller
{
    private function projects(Request $request): Builder
    {
        return Project::query()->when(! $request->user()->hasPermission('clients.view'), fn ($q) => $q->whereHas('users', fn ($q) => $q->where('users.id', $request->user()->id)));
    }

    public function index(Request $request): View
    {
        $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:unverified,queued,found,not_found,failed']);
        $projects = $this->projects($request)->orderBy('name')->get(['id', 'name']);
        $backlinks = Backlink::whereIn('project_id', $projects->pluck('id'))->with('project')->when($request->q, fn ($q, $term) => $q->where('source_url', 'like', '%'.$term.'%'))->when($request->status, fn ($q, $status) => $q->where('status', $status))->latest()->paginate(20)->withQueryString();

        return view('seo.backlinks', compact('backlinks', 'projects'));
    }

    private function rules(): array
    {
        return ['source_url' => 'required|url:http,https|max:2048', 'target_url' => 'required|url:http,https|max:2048', 'anchor' => 'nullable|string|max:300', 'campaign' => 'nullable|string|max:150'];
    }

    private function save(Project $project, array $data, string $source): void
    {
        try {
            $data['source_url'] = app(PublicUrl::class)->normalize($data['source_url']);
            $data['target_url'] = app(PublicUrl::class)->normalize($data['target_url']);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['source_url' => $e->getMessage()]);
        }
        $key = ['project_id' => $project->id, 'url_pair_hash' => hash('sha256', $data['source_url'].'|'.$data['target_url'])];
        $backlink = Backlink::firstOrCreate($key, $data + ['user_id' => auth()->id(), 'source' => $source]);
        if ($backlink->wasRecentlyCreated) {
            Activity::record('backlink.saved', $backlink);
        }
    }

    public function store(Request $request, ToolAccess $access): RedirectResponse
    {
        $data = $request->validate($this->rules() + ['project_id' => 'required|integer']);
        $project = Project::findOrFail($data['project_id']);
        $access->authorizeRun($request->user(), 'backlink-manager', $project);
        unset($data['project_id']);
        DB::transaction(function () use ($project, $data, $access): void {
            $access->consume('backlink-manager');
            $this->save($project, $data, 'Manual');
        });

        return back()->with('success', 'Backlink saved. Exact source/target duplicates are retained once per project.');
    }

    public function import(Request $request, ToolAccess $access): RedirectResponse
    {
        $data = $request->validate(['project_id' => 'required|integer', 'file' => 'required|file|extensions:csv,xlsx|max:256']);
        $project = Project::findOrFail($data['project_id']);
        $access->authorizeRun($request->user(), 'backlink-manager', $project);
        $rows = [];
        foreach (app(TableFile::class)->read($request->file('file'), ['source_url', 'target_url'], ['source_url', 'target_url', 'anchor', 'campaign']) as $row) {
            $validator = validator($row, $this->rules());
            if ($validator->fails()) {
                throw ValidationException::withMessages(['file' => 'Invalid backlink row '.(count($rows) + 2).': '.$validator->errors()->first()]);
            }$rows[] = $validator->validated();
        }
        if (! $rows) {
            throw ValidationException::withMessages(['file' => 'No backlinks supplied.']);
        }
        $source = strtoupper($request->file('file')->getClientOriginalExtension()).' import';
        DB::transaction(function () use ($rows, $project, $access, $source): void {
            $access->consume('backlink-manager');
            foreach ($rows as $row) {
                $this->save($project, $row, $source);
            }
        });

        return back()->with('success', 'Backlink list imported. Duplicate URL pairs were skipped.');
    }

    public function show(Backlink $backlink): View
    {
        Gate::authorize('view', $backlink->project);
        $verifications = $backlink->verifications()->latest()->paginate(20);

        return view('seo.backlink', compact('backlink', 'verifications'));
    }

    public function verify(Backlink $backlink, Request $request, ToolAccess $access): RedirectResponse
    {
        $access->authorizeRun($request->user(), 'backlink-manager', $backlink->project);
        DB::transaction(function () use ($backlink, $access): void {
            $access->consume('backlink-manager');
            Backlink::whereKey($backlink->id)->lockForUpdate()->firstOrFail();
            if ($backlink->verifications()->whereIn('status', ['queued', 'running'])->exists()) {
                throw ValidationException::withMessages(['backlink' => 'Verification is already pending.']);
            }
            $verification = $backlink->verifications()->create(['user_id' => auth()->id(), 'status' => 'queued']);
            $backlink->update(['status' => 'queued']);
            VerifyBacklink::dispatch($backlink->agency_id, $verification->id)->afterCommit();
            Activity::record('backlink.verification_queued', $backlink);
        });

        return back()->with('success', 'Backlink verification queued.');
    }

    public function export(Request $request): StreamedResponse
    {
        $ids = $this->projects($request)->select('projects.id');

        return response()->streamDownload(app(TenantContext::class)->wrap(function () use ($ids): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['source_url', 'target_url', 'anchor', 'campaign', 'status', 'source'], ',', '"', '');
            foreach (Backlink::whereIn('project_id', $ids)->lazyById(100) as $backlink) {
                $cells = $backlink->only(['source_url', 'target_url', 'anchor', 'campaign', 'status', 'source']);
                fputcsv($file, array_map(fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'".$v : $v, $cells), ',', '"', '');
            }fclose($file);
        }), 'backlinks.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
