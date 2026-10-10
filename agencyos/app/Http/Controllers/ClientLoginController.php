<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Services\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ClientLoginController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($client->portal_user_id)],
            'password' => [$client->portal_user_id ? 'nullable' : 'required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'enabled' => 'required|boolean',
        ]);
        DB::transaction(function () use ($client, $data): void {
            $client = Client::whereKey($client->id)->lockForUpdate()->firstOrFail();
            $user = $client->portalUser ?? new User;
            $user->fill(['name' => $client->name, 'email' => $data['email']]);
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->is_active = (bool) $data['enabled'];
            $user->save();
            $user->agencies()->syncWithoutDetaching([$client->agency_id => ['role_id' => Role::where('name', 'client')->firstOrFail()->id]]);
            $client->portal_user_id = $user->id;
            $client->save();
            if (! $user->is_active || ! empty($data['password'])) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            Activity::record('client.login_updated', $client, ['enabled' => $user->is_active]);
        });

        return back()->with('success', 'Client login saved. The client can sign in to view only their reports and backlinks.');
    }
}
