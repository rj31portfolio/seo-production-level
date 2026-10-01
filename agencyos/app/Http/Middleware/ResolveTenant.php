<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;

class ResolveTenant
{
    public function handle(Request $request, Closure $next)
    {
        $context = app(TenantContext::class);
        $context->clear();
        abort_unless($request->user()?->is_active, 403, 'Your account is inactive.');
        $agency = $request->user()->agencies()->where('agencies.id', $request->session()->get('agency_id'))->first();
        abort_unless($agency, 403, 'No authorized agency selected.');
        abort_unless($agency->status === 'active', 403, 'This agency is suspended.');
        $context->set($agency);
        try {
            return $next($request);
        } finally {
            $context->clear();
        }
    }
}
