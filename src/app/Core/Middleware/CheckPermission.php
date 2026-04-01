<?php

namespace App\Core\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    /** Usage: ->middleware('permission:User.create') */
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        [$doctype, $action] = explode('.', $permission, 2) + [null, 'read'];

        if (!app(\App\Core\Services\PermissionService::class)->can($doctype, $action, $user)) {
            return response()->json(['message' => "Forbidden. Required: {$permission}"], 403);
        }

        return $next($request);
    }
}
