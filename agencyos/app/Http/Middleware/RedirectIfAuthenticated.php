<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string ...$guards): Response
    {
        foreach ($guards ?: [null] as $guard) {
            if (Auth::guard($guard)->check()) {
                return redirect()->route('dashboard');
            }
        }

        return $next($request);
    }
}
