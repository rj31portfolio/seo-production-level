<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\Activity;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100']);
        $employees = app(TenantContext::class)->agency()->users()->when($request->q, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))->orderBy('name')->paginate(20)->withQueryString();

        return view('employees', ['employees' => $employees, 'roles' => Role::whereNotIn('name', ['agency_owner', 'client'])->get(), 'allRoles' => Role::pluck('name', 'id')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email', 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()], 'role_id' => 'required|integer']);
        $role = Role::whereKey($data['role_id'])->whereNotIn('name', ['agency_owner', 'client'])->firstOrFail();
        DB::transaction(function () use ($data, $role) {
            $user = User::create($data);
            app(TenantContext::class)->agency()->users()->attach($user, ['role_id' => $role->id]);
            Activity::record('employee.created', $user, ['role' => $role->name]);
        });

        return back()->with('success', 'Team member created. Share their initial password securely.');
    }

    public function update(Request $request, int $user)
    {
        $data = $request->validate(['role_id' => 'required|integer']);
        $member = app(TenantContext::class)->agency()->users()->where('users.id', $user)->firstOrFail();
        abort_if($member->id === $request->user()->id || Role::find($member->pivot->role_id)->name === 'agency_owner', 403, 'Owner permissions cannot be changed here.');
        $role = Role::whereKey($data['role_id'])->whereNotIn('name', ['agency_owner', 'client'])->firstOrFail();
        DB::transaction(function () use ($member, $role) {
            app(TenantContext::class)->agency()->users()->updateExistingPivot($member->id, ['role_id' => $role->id]);
            Activity::record('employee.role_changed', $member, ['role' => $role->name]);
        });

        return back()->with('success', 'Team role updated.');
    }

    public function destroy(Request $request, int $user)
    {
        $member = app(TenantContext::class)->agency()->users()->where('users.id', $user)->firstOrFail();
        abort_if($member->id === $request->user()->id || Role::find($member->pivot->role_id)->name === 'agency_owner', 403, 'Owner access cannot be removed here.');
        DB::transaction(function () use ($member) {
            app(TenantContext::class)->agency()->users()->detach($member);
            Activity::record('employee.removed', $member);
        });

        return back()->with('success', 'Agency access revoked. Historical records are preserved.');
    }
}
