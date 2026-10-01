<?php

namespace App\Http\Controllers;

use App\Models\Keyword;
use App\Models\Project;
use App\Models\RankingEntry;
use App\Models\SeoToolRun;
use App\Services\Activity;
use App\Services\Seo\ToolAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RankingController extends Controller
{
    private function projects(Request $request): Builder
    {
        return Project::query()->when(! $request->user()->hasPermission('clients.view'), fn ($q) => $q->whereHas('users', fn ($q) => $q->where('users.id', $request->user()->id)));
    }

    public function index(Request $request): View
    {
        $request->validate(['project_id' => 'nullable|integer', 'q' => 'nullable|string|max:100']);
        $projects = $this->projects($request)->orderBy('name')->get(['id', 'name']);
        $keywords = Keyword::whereIn('project_id', $projects->pluck('id'))->when($request->project_id, fn ($q, $id) => $q->where('project_id', $id))->when($request->q, fn ($q, $term) => $q->where('keyword', 'like', '%'.$term.'%'))->with('project')->withCount('entries')->orderBy('keyword')->paginate(20)->withQueryString();

        return view('seo.rankings', compact('keywords', 'projects'));
    }

    public function store(Request $request, ToolAccess $access): RedirectResponse
    {
        $data = $request->validate(['project_id' => 'required|integer', 'keyword' => 'required|string|max:200', 'target_url' => 'nullable|url:http,https|max:2048']);
        $project = Project::findOrFail($data['project_id']);
        $access->authorizeRun($request->user(), 'rank-tracker', $project);
        DB::transaction(function () use ($data, $access): void {
            $access->consume('rank-tracker');
            $keyword = Keyword::firstOrCreate(['project_id' => $data['project_id'], 'keyword' => mb_strtolower(trim($data['keyword']))], ['target_url' => $data['target_url'] ?? null]);
            Activity::record('keyword.saved', $keyword);
        });

        return back()->with('success', 'Keyword saved. Add a verified observation to track it.');
    }

    public function saveRun(SeoToolRun $run, ToolAccess $access): RedirectResponse
    {
        Gate::authorize('view', $run);
        abort_unless($run->project && $run->status === 'completed', 422, 'Use a completed project-linked keyword run.');
        $access->authorizeRun(auth()->user(), 'rank-tracker', $run->project);
        DB::transaction(function () use ($run, $access): void {
            $access->consume('rank-tracker');
            foreach ($run->results as $result) {
                foreach ($result->data['keywords'] ?? [] as $row) {
                    Keyword::firstOrCreate(['project_id' => $run->project_id, 'keyword' => mb_substr(mb_strtolower($row['keyword']), 0, 200)]);
                }
            }Activity::record('keywords.saved', $run);
        });

        return redirect()->route('seo.rankings.index')->with('success', 'Keywords saved to the project. Rankings remain unavailable until observations are supplied.');
    }

    public function show(Keyword $keyword, Request $request): View
    {
        Gate::authorize('view', $keyword->project);
        $data = $request->validate(['country' => 'nullable|string|size:2', 'location' => 'nullable|string|max:100', 'device' => 'nullable|in:desktop,mobile,tablet', 'search_engine' => 'nullable|string|max:50']);
        $context = ['country' => strtoupper($data['country'] ?? 'IN'), 'location' => $data['location'] ?? '', 'device' => $data['device'] ?? 'desktop', 'search_engine' => mb_strtolower($data['search_engine'] ?? 'google')];
        $entries = $keyword->entries()->where($context)->orderByDesc('observed_on')->paginate(50)->withQueryString();
        $latest = $keyword->entries()->where($context)->orderByDesc('observed_on')->limit(2)->get();
        $current = $latest[0]->position ?? null;
        $previous = $latest[1]->position ?? null;
        $change = $current !== null && $previous !== null ? $previous - $current : null;
        $history = $keyword->entries()->where($context)->orderByDesc('observed_on')->limit(180)->get()->reverse()->values()->map(fn ($entry) => ['date' => $entry->observed_on->format('Y-m-d'), 'position' => $entry->position]);
        $best = $keyword->entries()->where($context)->min('position');
        $worst = $keyword->entries()->where($context)->max('position');

        return view('seo.ranking-history', compact('keyword', 'context', 'entries', 'current', 'previous', 'change', 'best', 'worst', 'history'));
    }

    private function rules(): array
    {
        return ['observed_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'position' => 'nullable|integer|min:1|max:1000', 'country' => 'required|alpha|size:2', 'location' => 'nullable|string|max:100', 'device' => 'required|in:desktop,mobile,tablet', 'search_engine' => 'required|string|max:50', 'evidence' => 'nullable|string|max:2048'];
    }

    private function observation(Keyword $keyword, array $data, string $source): void
    {
        $context = ['keyword_id' => $keyword->id, 'observed_on' => $data['observed_on'], 'country' => strtoupper($data['country']), 'location' => $data['location'] ?? '', 'device' => $data['device'], 'search_engine' => mb_strtolower(trim($data['search_engine']))];
        if (RankingEntry::where(array_diff_key($context, ['observed_on' => true]))->whereDate('observed_on', $data['observed_on'])->exists()) {
            throw ValidationException::withMessages(['observed_on' => 'An observation already exists for this keyword, date and search context.']);
        }
        $entry = RankingEntry::create($context + ['user_id' => auth()->id(), 'position' => $data['position'] ?? null, 'source' => $source, 'evidence' => $data['evidence'] ?? null]);
        Activity::record('ranking.recorded', $entry);
    }

    public function entry(Keyword $keyword, Request $request, ToolAccess $access): RedirectResponse
    {
        $access->authorizeRun($request->user(), 'rank-tracker', $keyword->project);
        $data = $request->validate($this->rules());
        DB::transaction(function () use ($keyword, $data, $access): void {
            $access->consume('rank-tracker');
            $keyword->lockForUpdate()->findOrFail($keyword->id);
            $this->observation($keyword, $data, 'Manual');
        });

        return back()->with('success', 'Manual ranking observation saved.');
    }

    public function import(Request $request, ToolAccess $access): RedirectResponse
    {
        $data = $request->validate(['project_id' => 'required|integer', 'file' => 'required|file|extensions:csv|max:256']);
        $project = Project::findOrFail($data['project_id']);
        $access->authorizeRun($request->user(), 'rank-tracker', $project);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $rows = [];
        try {
            $header = fgetcsv($handle, 65536, ',', '"', '');
            if (! $header || array_diff(['keyword', 'observed_on', 'position', 'country', 'location', 'device', 'search_engine'], $header)) {
                throw ValidationException::withMessages(['file' => 'Required CSV headers: keyword, observed_on, position, country, location, device, search_engine.']);
            }
            while (($cells = fgetcsv($handle, 65536, ',', '"', '')) !== false) {
                if (count($rows) >= 500 || count($cells) !== count($header)) {
                    throw ValidationException::withMessages(['file' => 'Import at most 500 complete rows.']);
                }$row = array_combine($header, $cells);
                $row['position'] = $row['position'] === '' ? null : $row['position'];
                $row['location'] = $row['location'] ?: null;
                $validator = validator($row, $this->rules() + ['keyword' => 'required|string|max:200']);
                if ($validator->fails()) {
                    throw ValidationException::withMessages(['file' => 'Invalid CSV row '.(count($rows) + 2).': '.$validator->errors()->first()]);
                }$rows[] = $validator->validated();
            }
        } finally {
            fclose($handle);
        }
        if (! $rows) {
            throw ValidationException::withMessages(['file' => 'CSV contains no observations.']);
        }
        DB::transaction(function () use ($rows, $project, $access): void {
            $access->consume('rank-tracker');
            foreach ($rows as $row) {
                $keyword = Keyword::firstOrCreate(['project_id' => $project->id, 'keyword' => mb_strtolower(trim($row['keyword']))]);
                $this->observation($keyword, $row, 'CSV import');
            }
        });

        return back()->with('success', count($rows).' imported observations saved.');
    }

    public function export(Request $request): StreamedResponse
    {
        $projects = $this->projects($request)->select('projects.id');

        return response()->streamDownload(function () use ($projects): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['keyword', 'observed_on', 'position', 'country', 'location', 'device', 'search_engine', 'source'], ',', '"', '');
            foreach (RankingEntry::whereHas('keyword', fn ($q) => $q->whereIn('project_id', $projects))->with('keyword')->lazyById(100) as $entry) {
                $cells = [$entry->keyword->keyword, $entry->observed_on->format('Y-m-d'), $entry->position, $entry->country, $entry->location, $entry->device, $entry->search_engine, $entry->source];
                fputcsv($file,array_map(fn ($value) => preg_match('/^[=+\-@\t\r]/',(string) $value) ? "'".$value : $value,$cells),',','"','');
            }fclose($file);
        }, 'ranking-observations.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
