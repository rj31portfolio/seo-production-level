<?php

namespace App\Http\Middleware;

use App\Services\WebInstaller;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InstallationMode
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $installer = app(WebInstaller::class);
        if ($installer->enabled()) {
            config(['app.key' => $installer->state()['key'], 'app.debug' => false, 'session.driver' => 'file', 'session.encrypt' => true, 'session.cookie' => 'agencyos_setup', 'session.secure' => $request->isSecure(), 'cache.default' => 'file']);
            if (! $request->is('install', 'install/*')) {
                return redirect('/install');
            }
            if (! $request->isSecure() && ! app()->environment('local', 'testing')) {
                abort(403, 'Enable HTTPS on this domain before entering installation credentials.');
            }
        } elseif ($request->is('install', 'install/*')) {
            abort(404);
        }

        $response = $next($request);
        if ($request->is('install', 'install/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('X-Frame-Options', 'DENY');
        }

        return $response;
    }
}
