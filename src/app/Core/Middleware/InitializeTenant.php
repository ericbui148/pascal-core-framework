<?php

namespace App\Core\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * InitializeTenant — resolves the current tenant from subdomain or X-Tenant-ID header.
 * In single-tenant mode (default), this is a no-op pass-through.
 */
class InitializeTenant
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Single-tenant mode: nothing to do.
        // Multi-tenant: add stancl/tenancy initialization here.
        return $next($request);
    }
}
