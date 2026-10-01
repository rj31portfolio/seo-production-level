<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportPdf;
use App\Models\Report;
use App\Models\Role;
use App\Models\SeoTask;
use App\Models\SeoToolRun;
use App\Services\Activity;
use App\Services\Seo\ReportBuilder;
use App\Services\Seo\ToolAccess;
use App\Services\Seo\ToolRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SeoWorkController extends Controller
{
    public function tasks(Request $request): View
    {
        $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:pending,in_progress,review,completed,cancelled']);
        $tasks = SeoTask::with(['project.users', 'assignee'])->when(! $request->user()->hasPermission('tasks.assign'), fn ($q) => $q->where('assigned_to', $request->user()->id)->whereHas('project.users', fn ($q) => $q->where('users.id', $request->user()->id)))->when($request->q, fn ($q, $term) => $q->where('title', 'like', '%'.$term.'%'))->when($request->status, fn ($q, $status) => $q->where('status', $status))->orderBy('due_at')->paginate(20)->withQueryString();

        return view('seo.tasks', compact('tasks'));
    }

    public function generateTasks(SeoToolRun $run): RedirectResponse
    {
        Gate::authorize('view', $run);
        Gate::authorize('tasks.create');
        abort_unless($run->status === 'completed' && $run->project, 422, 'A completed project-linked tool run is required.');
        abort_unless(! $run->client->subscription || $run->client->subscription->operational(), 403, 'Client service is not operational.');
        app(ToolAccess::class)->authorizeRun(auth()->user(), 'task-generator', $run->project);
        $created = DB::transaction(function () use ($run) {
            app(ToolAccess::class)->consume('task-generator');
            $created = 0;
            foreach ($run->results()->lazyById(100) as $result) {
                foreach ($result->data['checks'] ?? [] as $check) {
                    if ($check['passed'] || $check['severity'] === 'information') {
                        continue;
                    }
                    $key = hash('sha256', $run->id.'|'.$result->id.'|'.$check['rule']);
                    $developer = in_array($check['category'] ?? '', ['technical', 'indexability', 'schema'], true);
                    $assignee = $run->project->users()->whereHas('agencies', fn ($q) => $q->where('agencies.id', $run->agency_id)->where('agency_users.role_id', Role::where('name', $developer ? 'developer' : 'seo_executive')->value('id')))->orderBy('users.id')->first();
                    $task = SeoTask::firstOrCreate(['deduplication_key' => $key], ['project_id' => $run->project_id, 'client_id' => $run->client_id, 'website_id' => $run->website_id, 'seo_tool_run_id' => $run->id, 'assigned_to' => $assignee?->id, 'created_by' => auth()->id(), 'title' => ucwords(str_replace('_', ' ', $check['rule'])), 'description' => $check['recommendation']."\nURL: ".($result->url ?? 'Supplied input'), 'category' => $check['category'] ?? 'seo', 'priority' => $check['severity'], 'status' => 'pending', 'due_at' => now()->addHours(48)]);
                    if ($task->wasRecentlyCreated) {
                        $created++;
                        Activity::record('seo_task.created', $task);
                    }
                }
            }

            return $created;
        });

        return back()->with('success', $created.' tasks created. Existing tasks from this run were retained.');
    }

    public function updateTask(Request $request, SeoTask $task): RedirectResponse
    {
        Gate::authorize('view', $task->project);
        abort_unless(! $task->project->client->subscription || $task->project->client->subscription->operational(), 403, 'Client service is not operational.');
        $data = $request->validate(['status' => 'required|in:pending,in_progress,review,completed,cancelled', 'assigned_to' => 'nullable|integer', 'completion_note' => 'nullable|string|max:5000']);
        if (! $request->user()->hasPermission('tasks.assign')) {
            Gate::authorize('tasks.complete');
            abort_unless($task->assigned_to === $request->user()->id && in_array($data['status'], ['in_progress', 'review'], true) && in_array($task->status, ['pending', 'in_progress', 'review'], true), 403);
            unset($data['assigned_to']);
        } elseif (isset($data['assigned_to']) && ! $task->project->users()->where('users.id', $data['assigned_to'])->exists()) {
            throw ValidationException::withMessages(['assigned_to' => 'Assign a member of this project.']);
        }
        $data['completed_at'] = $data['status'] === 'completed' ? now() : null;
        DB::transaction(function () use ($task, $data) {
            $task->update($data);
            Activity::record('seo_task.updated', $task, $data);
        });

        return back()->with('success', 'Task updated.');
    }

    public function generateReport(SeoToolRun $run, ReportBuilder $builder): RedirectResponse
    {
        Gate::authorize('view', $run);
        app(ToolAccess::class)->authorizeRun(auth()->user(), 'report-generator', $run->project);
        abort_unless($run->status === 'completed', 422, 'Use a completed tool run.');
        $snapshot = $builder->snapshot($run);
        $report = DB::transaction(function () use ($run, $snapshot) {
            app(ToolAccess::class)->consume('report-generator');
            $report = Report::create(['user_id' => auth()->id(), 'project_id' => $run->project_id, 'seo_tool_run_id' => $run->id, 'title' => ToolRegistry::get($run->tool)['name'].' report', 'snapshot' => $snapshot]);
            Activity::record('seo_report.created', $report);

            return $report;
        });

        return redirect()->route('seo.reports.show', $report)->with('success', 'Report snapshot saved.');
    }

    public function reports(Request $request, ToolAccess $access): View
    {
        $reports = Report::whereIn('seo_tool_run_id', $access->visibleRuns($request->user())->select('seo_tool_runs.id'))->latest()->paginate(20);

        return view('seo.reports', compact('reports'));
    }

    public function report(Report $report): View
    {
        Gate::authorize('view', $report->run);

        return view('seo.report', compact('report'));
    }

    public function html(Report $report): Response
    {
        Gate::authorize('view', $report->run);

        return response()->view('seo.report-document', compact('report'))->header('Content-Disposition', 'attachment; filename="seo-report-'.$report->id.'.html"');
    }

    public function preparePdf(Report $report, Request $request): RedirectResponse
    {
        Gate::authorize('view', $report->run);
        DB::transaction(function () use ($report, $request): void {
            $report = Report::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_if(in_array($report->pdf_status, ['queued', 'running'], true), 422, 'PDF generation is already pending.');
            $report->update(['pdf_status' => 'queued', 'pdf_error' => null, 'pdf_requested_by' => $request->user()->id]);
            GenerateReportPdf::dispatch($report->agency_id, $report->id)->afterCommit();
        });

        return back()->with('success', 'PDF generation queued.');
    }

    public function pdf(Report $report): BinaryFileResponse
    {
        Gate::authorize('view', $report->run);
        abort_unless($report->pdf_status === 'completed' && $report->pdf_path && Storage::disk('local')->exists($report->pdf_path), 409, 'PDF is not ready. Queue generation from the report page.');

        return response()->download(Storage::disk('local')->path($report->pdf_path), 'seo-report-'.$report->id.'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
