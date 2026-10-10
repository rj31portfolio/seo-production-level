<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Role;
use App\Models\User;
use App\Services\Activity;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($data + ['is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'These credentials could not be verified.']);
        }
        $request->session()->regenerate();
        $agency = $request->user()->agencies()->where('status', 'active')->first();
        $request->session()->put('agency_id', $agency?->id);
        Activity::record('auth.login', $request->user(), [], $agency?->id);

        if ($request->user()->is_super_admin) {
            return redirect()->route('super-admin');
        }

        return redirect()->route($agency && Role::find($agency->pivot->role_id)?->name === 'client' ? 'portal.reports.index' : 'dashboard');
    }

    public function register(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'agency_name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email', 'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()]]);
        [$user, $agency] = DB::transaction(function () use ($data) {
            $agency = Agency::create(['name' => $data['agency_name'], 'timezone' => config('agencyos.timezone'), 'currency' => config('agencyos.currency')]);
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $agency->users()->attach($user, ['role_id' => Role::where('name', 'agency_owner')->firstOrFail()->id]);
            Activity::record('agency.registered', $agency, [], $agency->id);

            return [$user, $agency];
        });
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('agency_id', $agency->id);

        return redirect('/dashboard')->with('success', 'Your agency workspace is ready.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function forgot(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        Password::sendResetLink($request->only('email'));

        return back()->with('success', 'If this account exists, a password reset link has been sent.');
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['token' => 'required', 'email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()]]);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect('/login')->with('success', 'Password updated. Please sign in.');
    }

    public function switchAgency(Request $request)
    {
        $request->validate(['agency_id' => 'required|integer']);
        $agency = $request->user()->agencies()->where('agencies.id', $request->integer('agency_id'))->where('status', 'active')->firstOrFail();
        $request->session()->put('agency_id', $agency->id);
        $request->session()->regenerate();

        return redirect('/dashboard');
    }
}
