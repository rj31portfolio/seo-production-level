<?php

namespace App\Http\Controllers;

use App\Services\WebInstaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InstallationController extends Controller
{
    public function show(Request $request, WebInstaller $installer): View
    {
        return view('auth.install', ['step' => $request->session()->has('installation.database') && $request->query('step') !== '1' ? 2 : 1, 'checks' => $installer->requirements(), 'database' => $request->session()->get('installation.database')]);
    }

    public function database(Request $request, WebInstaller $installer): RedirectResponse
    {
        $data = $request->validate([
            'setup_token' => 'required|string|max:128', 'app_url' => 'required|url|starts_with:https://|max:255',
            'db_host' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9.:-]+$/'],
            'db_port' => 'required|integer|min:1|max:65535', 'db_database' => 'required|string|max:64|regex:/^[a-zA-Z0-9_-]+$/',
            'db_username' => ['required', 'string', 'max:100', 'not_regex:/[\r\n\x00]/'],
            'db_password' => ['nullable', 'string', 'max:255', 'not_regex:/[\r\n\x00]/'],
        ]);
        if (! $installer->authorized($data['setup_token'])) {
            throw ValidationException::withMessages(['setup_token' => 'The private setup key is incorrect.']);
        }
        $url = parse_url($data['app_url']);
        if (isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || ! empty(trim($url['path'] ?? '', '/'))) {
            throw ValidationException::withMessages(['app_url' => 'Enter your HTTPS domain without a path, query or login credentials.']);
        }
        if (in_array(false, $installer->requirements(), true)) {
            throw ValidationException::withMessages(['installation' => 'Resolve the server checks below before continuing.']);
        }
        $installer->connect($data);
        $installer->assertDatabaseAvailable($data);
        unset($data['setup_token']);
        $request->session()->regenerate();
        $request->session()->put('installation.database', $data);
        $request->session()->put('installation.authorization', $installer->state()['token_hash']);

        return redirect()->route('install.show');
    }

    public function finish(Request $request, WebInstaller $installer): RedirectResponse
    {
        abort_unless($request->session()->has('installation.database') && hash_equals($installer->state()['token_hash'], $request->session()->get('installation.authorization', '')), 403);
        $data = $request->validate([
            'name' => 'required|string|max:255', 'agency_name' => ['required', 'string', 'max:255', 'not_regex:/[\r\n\x00]/'],
            'email' => 'required|email|max:255', 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'timezone' => 'required|timezone', 'currency' => 'required|regex:/^[A-Z]{3}$/',
            'support_email' => 'nullable|email|max:255', 'address' => 'nullable|string|max:600',
            'mail_host' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9.-]+$/'],
            'mail_port' => 'nullable|integer|min:1|max:65535', 'mail_scheme' => 'nullable|in:smtp,smtps',
            'mail_username' => ['nullable', 'string', 'max:255', 'not_regex:/[\r\n\x00]/'],
            'mail_password' => ['nullable', 'string', 'max:255', 'not_regex:/[\r\n\x00]/'],
        ]);
        try {
            $installer->finish($request->session()->get('installation.database'), $data);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report(new \RuntimeException('Web installation failed: '.$exception::class));
            throw ValidationException::withMessages(['installation' => 'Installation could not finish. Check database privileges and file permissions. Your existing data has not been reset.']);
        }
        $request->session()->invalidate();

        return redirect('/login?installed=1');
    }
}
