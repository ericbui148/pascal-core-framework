<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Services\UserAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuthController extends Controller
{
    public function __construct(protected UserAuthService $auth) {}

    // POST /api/v1/auth/register
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'full_name'             => 'required|string|max:255',
            'email'                 => 'required|email|max:255',
            'password'              => 'required|string',
            'password_confirmation' => 'required|string',
            'name'                  => 'sometimes|string|max:120',
        ]);

        $result = $this->auth->register($request->all());

        return response()->json([
            'message' => 'Registration successful.',
            'user'    => $result['user'],
            'token'   => $result['token'],
        ], 201);
    }

    // POST /api/v1/auth/login
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'       => 'required|email',
            'password'    => 'required|string',
            'remember_me' => 'sometimes|boolean',
        ]);

        $result = $this->auth->login(
            $request->only('email', 'password', 'remember_me'),
            $request->ip(),
            $request->userAgent() ?? '',
        );

        return response()->json([
            'message'    => 'Login successful.',
            'user'       => $result['user'],
            'token'      => $result['token'],
            'expires_at' => $result['expires_at'],
        ]);
    }

    // POST /api/v1/auth/logout
    public function logout(Request $request): JsonResponse
    {
        $user = $request->attributes->get('pascal_user');
        $this->auth->logout($user['_token_id'], $user);

        return response()->json(['message' => 'Logged out.']);
    }

    // POST /api/v1/auth/logout-all
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->attributes->get('pascal_user');
        $this->auth->logoutAllDevices($user['id']);

        return response()->json(['message' => 'Logged out from all devices.']);
    }

    // GET /api/v1/auth/me
    public function me(Request $request): JsonResponse
    {
        $user = $request->attributes->get('pascal_user');

        return response()->json(['user' => $this->auth->publicFields($user)]);
    }

    // POST /api/v1/auth/forgot-password
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $this->auth->forgotPassword($request->email);

        return response()->json([
            'message' => 'If that email exists, a reset link has been sent.',
        ]);
    }

    // POST /api/v1/auth/reset-password
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'                 => 'required|string',
            'email'                 => 'required|email',
            'password'              => 'required|string',
            'password_confirmation' => 'required|string',
        ]);

        $this->auth->resetPassword($request->all());

        return response()->json([
            'message' => 'Password reset successfully. Please log in with your new password.',
        ]);
    }

    // POST /api/v1/auth/change-password
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|string',
            'password_confirmation' => 'required|string',
        ]);

        $user = $request->attributes->get('pascal_user');

        $this->auth->changePassword(
            $user,
            $request->current_password,
            $request->password,
            $user['_token_id'],
        );

        return response()->json([
            'message' => 'Password changed. All other sessions have been revoked.',
        ]);
    }
}
