<?php

namespace App\Modules\User\Middleware;

use App\Modules\User\Services\UserAuthService;
use Closure;
use Illuminate\Http\Request;

/**
 * AuthenticatePascalUser — resolves the current user from our pascal_users table
 * using the token stored in personal_access_tokens.
 *
 * Replaces auth:sanctum for routes that need Pascal Platform users.
 * Sets $request->attributes['pascal_user'] so controllers can call $request->pascalUser().
 */
class AuthenticatePascalUser
{
    public function __construct(protected UserAuthService $auth) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = $this->auth->getUserByToken($token);

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user['status'] === 'Banned') {
            return response()->json(['message' => 'Account banned.'], 403);
        }

        // Attach user to request so controllers can retrieve it
        $request->attributes->set('pascal_user', $user);

        return $next($request);
    }
}
