<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\SeoMonitoring;
use App\Services\Seo\ToolAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function index(Request $request): View
    {
        $projects = Project::when(! $request->user()->hasPermission('clients.view'), fn ($q) => $q->whereHas('users', fn ($q) => $q->where('users.id', $request->user()->id)))->with('website')->orderBy('name')->get();
        $monitors = SeoMonitoring::whereIn('project_id', $projects->pluck('id'))->with('project')->paginate(20);

        return view('seo.monitoring', compact('monitors', 'projects'));
    }

    public function store(Request $request, ToolAccess $access): RedirectResponse
    {
        $data = $request->validate(['project_id' => 'required|integer', 'interval_minutes' => 'required|in:60,360,1440,10080', 'enabled' => 'required|boolean']);
        $project = Project::findOrFail($data['project_id']);
        $access->authorizeRun($request->user(), 'website-monitor', $project);
        SeoMonitoring::updateOrCreate(['project_id' => $project->id], ['user_id' => $request->user()->id, 'interval_minutes' => $data['interval_minutes'], 'enabled' => $data['enabled'], 'next_due_at' => now()]);

        return back()->with('success', 'Monitoring schedule saved. Checks require the scheduler and queue worker.');
    }
}
