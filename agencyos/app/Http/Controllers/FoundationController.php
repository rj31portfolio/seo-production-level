<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\Website;
use App\Services\Activity;
use App\Services\AgencyBranding;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FoundationController extends Controller
{
    public function dashboard()
    {
        if (auth()->user()->hasPermission('client.portal')) {
            return redirect()->route('portal.reports.index');
        }
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

    public function settings(): View
    {
        $agency = app(TenantContext::class)->agency();

        return view('agency-settings', ['agency' => $agency, 'branding' => app(AgencyBranding::class)->snapshot($agency)]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255', 'timezone' => 'required|timezone',
            'currency' => 'required|string|size:3|regex:/^[A-Z]{3}$/',
            'tagline' => 'nullable|string|max:160', 'address' => 'nullable|string|max:600',
            'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|starts_with:http://,https://|max:255', 'support_email' => 'nullable|email|max:255',
            'support_url' => 'nullable|url|starts_with:http://,https://|max:255',
            'accent_color' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/', 'report_footer' => 'nullable|string|max:300',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg|max:1024|dimensions:max_width=1600,max_height=1600',
            'remove_logo' => 'nullable|boolean',
        ]);
        if ($request->hasFile('logo') && $request->boolean('remove_logo')) {
            throw ValidationException::withMessages(['logo' => 'Choose a replacement logo or remove the current logo.']);
        }
        $agency = app(TenantContext::class)->agency();
        $newPath = null;
        if ($request->hasFile('logo')) {
            $newPath = $request->file('logo')->store('branding/'.$agency->id, 'local');
            if (! $newPath) {
                throw ValidationException::withMessages(['logo' => 'Logo storage failed. Please retry.']);
            }
        }
        try {
            $oldPath = DB::transaction(function () use ($agency, $data, $request, $newPath): ?string {
                $locked = Agency::whereKey($agency->id)->lockForUpdate()->firstOrFail();
                $branding = $locked->branding ?? [];
                $oldPath = $branding['logo_path'] ?? null;
                $branding = array_replace($branding, array_intersect_key($data, array_flip(AgencyBranding::FIELDS)));
                if ($newPath || $request->boolean('remove_logo')) {
                    $branding['logo_path'] = $newPath;
                }
                $locked->update(['name' => $data['name'], 'timezone' => $data['timezone'], 'currency' => $data['currency'], 'branding' => $branding]);
                Activity::record('agency.settings_updated', $locked, ['fields' => array_keys($data)]);

                return $oldPath;
            });
        } catch (\Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }
        if ($oldPath && ($newPath || $request->boolean('remove_logo'))) {
            Storage::disk('local')->delete($oldPath);
        }

        return back()->with('success', 'Agency settings saved.');
    }

    public function activity(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100']);
        $logs = ActivityLog::with('user')->where('agency_id', app(TenantContext::class)->id())->when($request->q, fn ($q, $term) => $q->where('action', 'like', '%'.$term.'%'))->latest('id')->paginate(25)->withQueryString();

        return view('activity', compact('logs'));
    }
}
