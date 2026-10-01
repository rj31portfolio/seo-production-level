<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\Website;
use App\Services\Activity;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FoundationController extends Controller
{
    public function dashboard()
    {
        $agency = app(TenantContext::class)->agency();
        $counts = ['Team members' => $agency->users()->count()];
        if (auth()->user()->hasPermission('clients.view')) {
            $counts += ['Active clients' => Client::where('status', 'active')->count(), 'Active projects' => Project::where('status', 'active')->count(), 'Websites' => Website::count()];
        } else {
            $counts += ['Assigned projects' => Project::whereHas('users', fn ($q) => $q->where('users.id', auth()->id()))->count()];
        }

        return view('dashboard', ['agency' => $agency, 'counts' => $counts, 'activity' => auth()->user()->hasPermission('activity.view') ? ActivityLog::with('user')->where('agency_id', $agency->id)->latest('id')->limit(8)->get() : collect()]);
    }

    public function superAdmin(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:active,suspended']);

        return view('super-admin', ['agencies' => Agency::query()->withCount('users')->when($request->q, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))->when($request->status, fn ($q, $status) => $q->where('status', $status))->latest()->paginate(20)->withQueryString(), 'counts' => ['Total agencies' => Agency::count(), 'Active agencies' => Agency::where('status', 'active')->count(), 'Suspended agencies' => Agency::where('status', 'suspended')->count(), 'Platform users' => User::count()]]);
    }

    public function agencyStatus(Request $request, Agency $agency)
    {
        $data = $request->validate(['status' => 'required|in:active,suspended']);
        DB::transaction(function () use ($agency, $data) {
            $before = $agency->status;
            $agency->update($data);
            Activity::record('agency.status_changed', $agency, ['before' => $before, 'after' => $data['status']], $agency->id);
        });

        return back()->with('success', 'Agency status updated.');
    }

    public function settings()
    {
        return view('agency-settings', ['agency' => app(TenantContext::class)->agency()]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'timezone' => 'required|timezone', 'currency' => 'required|string|size:3|regex:/^[A-Z]{3}$/']);
        DB::transaction(function () use ($data) {
            $agency = app(TenantContext::class)->agency();
            $agency->update($data);
            Activity::record('agency.settings_updated', $agency, $data);
        });

        return back()->with('success', 'Agency settings saved.');
    }

    public function activity(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100']);
        $logs = ActivityLog::with('user')->where('agency_id', app(TenantContext::class)->id())->when($request->q, fn ($q, $term) => $q->where('action', 'like', '%'.$term.'%'))->latest('id')->paginate(25)->withQueryString();

        return view('activity', compact('logs'));
    }
}
