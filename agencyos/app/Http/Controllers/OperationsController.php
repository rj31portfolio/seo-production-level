<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\Website;
use App\Services\Activity;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OperationsController extends Controller
{
    private function module(Request $request): string
    {
        return $request->route('module');
    }

    private function definition(Request $request): array
    {
        return config('operations.'.$this->module($request)) ?? abort(404);
    }

    private function record(Request $request)
    {
        return $this->definition($request)['model']::findOrFail($request->route('record'));
    }

    public function index(Request $request)
    {
        $module = $this->module($request);
        $definition = $this->definition($request);
        Gate::authorize($module.'.view');
        $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:active,paused,completed,archived', 'sort' => 'nullable|in:name,created_at', 'direction' => 'nullable|in:asc,desc']);
        $query = $definition['model']::query();
        if (! $request->user()->hasPermission('clients.view')) {
            if ($module === 'projects') {
                $query->whereHas('users', fn ($q) => $q->where('users.id', $request->user()->id));
            } elseif ($module === 'websites') {
                $query->whereHas('projects.users', fn ($q) => $q->where('users.id', $request->user()->id));
            }
        }
        $records = $query->when($request->q, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))->when($request->status, fn ($q, $status) => $q->where('status', $status))->orderBy($request->input('sort', 'created_at'), $request->input('direction', 'desc'))->paginate(20)->withQueryString();

        return view('operations.index', compact('module', 'definition', 'records'));
    }

    public function create(Request $request)
    {
        Gate::authorize($this->module($request).'.create');

        return $this->form($request, new ($this->definition($request)['model']));
    }

    public function show(Request $request)
    {
        $record = $this->record($request);
        Gate::authorize('view', $record);

        return view('operations.show', ['module' => $this->module($request), 'definition' => $this->definition($request), 'record' => $record]);
    }

    public function edit(Request $request)
    {
        $record = $this->record($request);
        Gate::authorize('update', $record);

        return $this->form($request, $record);
    }

    private function form(Request $request, $record)
    {
        return view('operations.form', ['module' => $this->module($request), 'definition' => $this->definition($request), 'record' => $record, 'clients' => Client::orderBy('name')->limit(500)->get(['id', 'name']), 'websites' => Website::orderBy('name')->limit(500)->get(['id', 'name', 'client_id']), 'employees' => app(TenantContext::class)->agency()->users()->orderBy('name')->get(['users.id', 'name'])]);
    }

    private function validateData(Request $request): array
    {
        $rules = [];
        foreach ($this->definition($request)['fields'] as $key => $field) {
            $rules[$key] = $field[2];
        }
        if ($this->module($request) === 'projects') {
            $rules['employees'] = 'nullable|array';
            $rules['employees.*'] = 'integer|distinct';
        }
        $data = $request->validate($rules);
        if (isset($data['client_id']) && ! Client::whereKey($data['client_id'])->exists()) {
            throw ValidationException::withMessages(['client_id' => 'Choose a client in your agency.']);
        }
        if (isset($data['client_id']) && ($subscription = Client::find($data['client_id'])->subscription) && ! $subscription->operational()) {
            throw ValidationException::withMessages(['client_id' => 'This client service period does not allow operational changes. Renew or reactivate it first.']);
        }
        if (isset($data['website_id']) && ! Website::whereKey($data['website_id'])->where('client_id', $data['client_id'])->exists()) {
            throw ValidationException::withMessages(['website_id' => 'Choose a website belonging to this client.']);
        }
        foreach ($data['employees'] ?? [] as $id) {
            if (! app(TenantContext::class)->agency()->users()->where('users.id', $id)->exists()) {
                throw ValidationException::withMessages(['employees' => 'Every employee must belong to your agency.']);
            }
        }

        return $data;
    }

    public function store(Request $request)
    {
        $module = $this->module($request);
        Gate::authorize($module.'.create');
        $data = $this->validateData($request);
        $record = DB::transaction(function () use ($request, $data, $module) {
            $record = new ($this->definition($request)['model']);
            $record->fill($data);
            if ($module === 'websites') {
                $record->verification_token = Str::random(64);
            }
            $record->save();
            $this->assign($record, $data);
            Activity::record($module.'.created', $record);

            return $record;
        });

        return redirect()->route($module.'.show', $record)->with('success', 'Record created.');
    }

    public function update(Request $request)
    {
        $record = $this->record($request);
        Gate::authorize('update', $record);
        $data = $this->validateData($request);
        DB::transaction(function () use ($record, $data, $request) {
            if ($record instanceof Website && $record->url !== $data['url']) {
                $record->verified_at = null;
                $record->verification_token = Str::random(64);
            }
            if ($record instanceof Website && $record->client_id != $data['client_id'] && $record->projects()->exists()) {
                throw ValidationException::withMessages(['client_id' => 'Reassign existing projects before changing this website client.']);
            }
            $record->fill($data);
            $changes = $record->getDirty();
            $record->save();
            $this->assign($record, $data);
            Activity::record($this->module($request).'.updated', $record, $changes);
        });

        return redirect()->route($this->module($request).'.show', $record)->with('success', 'Record saved.');
    }

    private function assign($record, array $data): void
    {
        if ($record instanceof Project) {
            $record->users()->sync(collect($data['employees'] ?? [])->mapWithKeys(fn ($id) => [$id => ['agency_id' => $record->agency_id]])->all());
        }
    }

    public function destroy(Request $request)
    {
        $record = $this->record($request);
        Gate::authorize('delete', $record);
        if ($record instanceof Client && ($record->projects()->exists() || $record->websites()->exists() || $record->subscription()->exists())) {
            return back()->withErrors(['record' => 'Archive this client while projects, websites, or subscriptions reference it.']);
        }
        if ($record instanceof Website && $record->projects()->exists()) {
            return back()->withErrors(['record' => 'Remove active projects before removing this website.']);
        }
        DB::transaction(function () use ($record, $request) {
            Activity::record($this->module($request).'.deleted', $record);
            $record->delete();
        });

        return redirect()->route($this->module($request).'.index')->with('success', 'Record removed. Historical data is preserved.');
    }
}
