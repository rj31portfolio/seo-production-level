<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\SeoTask;
use App\Services\Activity;
use App\Services\Seo\SpreadsheetExport;
use App\Services\Seo\TableFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SeoBulkController extends Controller
{
    private function projects(Request $request): Builder
    {
        return Project::query()->when(! $request->user()->hasPermission('clients.view'), fn ($query) => $query->whereHas('users', fn ($users) => $users->where('users.id', $request->user()->id)));
    }

    public function index(Request $request): View
    {
        $projects = $this->projects($request)->orderBy('name')->get();

        return view('seo.bulk-upload', compact('projects'));
    }

    public function template(string $type, SpreadsheetExport $excel): StreamedResponse
    {
        $headers = match ($type) {
            'backlinks' => ['source_url', 'target_url', 'anchor', 'campaign'],
            'rankings' => ['keyword', 'observed_on', 'position', 'country', 'location', 'device', 'search_engine', 'evidence'],
            'tasks' => ['task_id', 'title', 'status', 'completion_note'],
            default => abort(404),
        };
        Gate::authorize(match ($type) {
            'backlinks' => 'seo_tools.backlinks',
            'rankings' => 'seo_tools.rankings',
            'tasks' => 'tasks.assign',
        });

        return $excel->download($type.'-template.xlsx', $headers, fn (): array => []);
    }

    public function importTasks(Request $request): RedirectResponse
    {
        $data = $request->validate(['project_id' => 'required|integer', 'file' => 'required|file|extensions:csv,xlsx|max:256']);
        $project = Project::findOrFail($data['project_id']);
        Gate::authorize('view', $project);
        abort_if($project->client->subscription && ! $project->client->subscription->operational(), 403, 'Client service is not operational.');
        $rows = app(TableFile::class)->read($request->file('file'), ['task_id', 'status'], ['task_id', 'title', 'status', 'completion_note']);
        $validated = [];
        foreach ($rows as $index => $row) {
            $validator = validator($row, ['task_id' => 'required|integer|min:1', 'status' => 'required|in:pending,in_progress,review,completed,cancelled', 'completion_note' => 'nullable|string|max:5000']);
            if ($validator->fails()) {
                throw ValidationException::withMessages(['file' => 'Invalid progress row '.($index + 2).': '.$validator->errors()->first()]);
            }
            $row = $validator->validated();
            if (isset($validated[$row['task_id']])) {
                throw ValidationException::withMessages(['file' => 'Each task ID must appear once.']);
            }
            $validated[$row['task_id']] = $row;
        }
        DB::transaction(function () use ($validated, $project): void {
            $tasks = SeoTask::where('project_id', $project->id)->whereIn('id', array_keys($validated))->lockForUpdate()->get()->keyBy('id');
            if ($tasks->count() !== count($validated)) {
                throw ValidationException::withMessages(['file' => 'Every task must belong to the selected project. No rows were updated.']);
            }
            foreach ($validated as $id => $row) {
                $task = $tasks[$id];
                $changes = ['status' => $row['status'], 'completed_at' => $row['status'] === 'completed' ? ($task->completed_at ?? now()) : null];
                if (array_key_exists('completion_note', $row)) {
                    $changes['completion_note'] = $row['completion_note'];
                }
                $task->update($changes);
                Activity::record('seo_task.progress_imported', $task, $changes);
            }
        });

        return back()->with('success', count($validated).' task progress updates imported.');
    }

    public function exportTasks(Request $request, SpreadsheetExport $excel): StreamedResponse
    {
        $data = $request->validate(['project_id' => 'required|integer']);
        $project = $this->projects($request)->findOrFail($data['project_id']);
        Gate::authorize('view', $project);

        return $excel->download('task-progress-'.$project->id.'.xlsx', ['task_id', 'title', 'status', 'completion_note'], function () use ($project): iterable {
            foreach (SeoTask::where('project_id', $project->id)->lazyById(100) as $task) {
                yield [$task->id, $task->title, $task->status, $task->completion_note];
            }
        });
    }
}
