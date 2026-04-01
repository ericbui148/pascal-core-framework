<?php

namespace App\Modules\User\Controllers;

use App\Core\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function __construct(protected DocumentService $documents) {}

    // GET /api/v1/user/profile
    public function show(Request $request): JsonResponse
    {
        $user = $request->attributes->get('pascal_user');
        unset($user['password'], $user['remember_token'], $user['_token_id']);

        return response()->json(['data' => $user]);
    }

    // PUT /api/v1/user/profile
    public function update(Request $request): JsonResponse
    {
        $user = $request->attributes->get('pascal_user');

        $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'phone'     => 'sometimes|nullable|string|max:30',
            'email'     => 'sometimes|email|max:255',
        ]);

        $updated = $this->documents->update('User', $user['name'], $request->only('full_name', 'phone', 'email'), (object) $user);

        unset($updated['password'], $updated['remember_token']);

        return response()->json(['message' => 'Profile updated.', 'data' => $updated]);
    }

    // POST /api/v1/user/avatar
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate(['avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048']);

        $user = $request->attributes->get('pascal_user');

        // Remove old avatar
        if ($user['avatar'] ?? null) {
            Storage::disk('public')->delete($user['avatar']);
        }

        $path = $request->file('avatar')->store("avatars/{$user['id']}", 'public');

        DB::table('pascal_users')->where('name', $user['name'])->update([
            'avatar'     => $path,
            'updated_at' => now(),
        ]);

        return response()->json([
            'message'    => 'Avatar uploaded.',
            'avatar_url' => asset("storage/{$path}"),
        ]);
    }

    // DELETE /api/v1/user/avatar
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->attributes->get('pascal_user');

        if ($user['avatar'] ?? null) {
            Storage::disk('public')->delete($user['avatar']);
            DB::table('pascal_users')->where('name', $user['name'])->update([
                'avatar'     => null,
                'updated_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Avatar removed.']);
    }

    // GET /api/v1/user/login-history
    public function loginHistory(Request $request): JsonResponse
    {
        $user    = $request->attributes->get('pascal_user');
        $history = DB::table('pascal_login_histories')
            ->where('user_id', $user['id'])
            ->orderByDesc('logged_in_at')
            ->paginate(20);

        return response()->json([
            'data' => $history->items(),
            'meta' => [
                'current_page' => $history->currentPage(),
                'last_page'    => $history->lastPage(),
                'total'        => $history->total(),
            ],
        ]);
    }

    // GET /api/v1/user/sessions
    public function sessions(Request $request): JsonResponse
    {
        $user    = $request->attributes->get('pascal_user');
        $sessions = DB::table('personal_access_tokens')
            ->where('tokenable_type', 'pascal_user')
            ->where('tokenable_id', $user['id'])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get()
            ->map(fn ($t) => [
                'id'         => $t->id,
                'name'       => $t->name,
                'last_used'  => $t->last_used_at,
                'expires_at' => $t->expires_at,
                'is_current' => $t->id === $user['_token_id'],
            ]);

        return response()->json(['data' => $sessions]);
    }

    // DELETE /api/v1/user/sessions/{id}
    public function revokeSession(Request $request, int $tokenId): JsonResponse
    {
        $user = $request->attributes->get('pascal_user');

        if ($tokenId === $user['_token_id']) {
            return response()->json(['message' => 'Use /logout to revoke the current session.'], 400);
        }

        DB::table('personal_access_tokens')
            ->where('id', $tokenId)
            ->where('tokenable_type', 'pascal_user')
            ->where('tokenable_id', $user['id'])
            ->delete();

        return response()->json(['message' => 'Session revoked.']);
    }
}
