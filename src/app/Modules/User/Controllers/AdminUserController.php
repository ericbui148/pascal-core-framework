<?php

namespace App\Modules\User\Controllers;

use App\Core\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * AdminUserController — Admin management of User DocType records.
 *
 * All data mutations go through DocumentService → UserDocumentController,
 * which fires events and writes audit logs automatically.
 */
class AdminUserController extends Controller
{
    public function __construct(protected DocumentService $documents) {}

    private function actor(Request $request): object
    {
        return (object) $request->attributes->get('pascal_user');
    }

    // GET /api/v1/admin/users
    public function index(Request $request): JsonResponse
    {
        $filters = [];
        if ($request->filled('status')) $filters['status'] = $request->status;
        if ($request->filled('role'))   $filters['role']   = $request->role;

        $limit  = (int) $request->query('limit', 20);
        $offset = (int) $request->query('offset', 0);

        $query = DB::table('pascal_users')->whereNull('deleted_at');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('full_name', 'like', "%{$s}%")
                                       ->orWhere('email', 'like', "%{$s}%"));
        }
        if (!empty($filters)) {
            foreach ($filters as $k => $v) $query->where($k, $v);
        }

        $total = (clone $query)->count();
        $rows  = $query->orderByDesc('created_at')
            ->limit($limit)->offset($offset)
            ->get()
            ->map(fn ($r) => $this->safe((array) $r))
            ->toArray();

        return response()->json(['data' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset]);
    }

    // GET /api/v1/admin/users/{name}
    public function show(Request $request, string $name): JsonResponse
    {
        $user = $this->documents->get('User', $name);
        return response()->json(['data' => $this->safe($user)]);
    }

    // POST /api/v1/admin/users
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'full_name'             => 'required|string|max:255',
            'email'                 => 'required|email',
            'password'              => 'required|string|min:8',
            'password_confirmation' => 'required|string',
            'role'                  => 'sometimes|in:user,manager,admin',
            'status'                => 'sometimes|in:Active,Inactive',
        ]);

        if ($request->password !== $request->password_confirmation) {
            return response()->json(['message' => 'Validation failed.', 'errors' => ['password' => ['Confirmation does not match.']]], 422);
        }

        $data = $this->documents->create('User', $request->except('password_confirmation'), $this->actor($request));

        return response()->json(['message' => 'User created.', 'data' => $this->safe($data)], 201);
    }

    // PUT /api/v1/admin/users/{name}
    public function update(Request $request, string $name): JsonResponse
    {
        $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'email'     => 'sometimes|email',
            'role'      => 'sometimes|in:user,manager,admin',
            'status'    => 'sometimes|in:Active,Inactive,Banned',
            'phone'     => 'sometimes|nullable|string|max:30',
            'password'  => 'sometimes|string|min:8',
        ]);

        $data = $this->documents->update('User', $name, $request->all(), $this->actor($request));

        return response()->json(['message' => 'User updated.', 'data' => $this->safe($data)]);
    }

    // DELETE /api/v1/admin/users/{name}
    public function destroy(Request $request, string $name): JsonResponse
    {
        $actor = $this->actor($request);

        if ($name === $actor->name) {
            return response()->json(['message' => 'Cannot delete your own account.'], 422);
        }

        $this->documents->delete('User', $name, $actor);

        return response()->json(['message' => 'User deleted.']);
    }

    // POST /api/v1/admin/users/{name}/ban
    public function ban(Request $request, string $name): JsonResponse
    {
        $actor = $this->actor($request);

        $target = $this->documents->get('User', $name);

        if ($target['role'] === 'admin') {
            return response()->json(['message' => 'Cannot ban an administrator.'], 422);
        }

        $this->documents->update('User', $name, ['status' => 'Banned'], $actor);

        // Revoke all tokens
        DB::table('personal_access_tokens')
            ->where('tokenable_type', 'pascal_user')
            ->where('tokenable_id', $target['id'])
            ->delete();

        return response()->json(['message' => "User {$name} has been banned."]);
    }

    // POST /api/v1/admin/users/{name}/unban
    public function unban(Request $request, string $name): JsonResponse
    {
        $this->documents->update('User', $name, ['status' => 'Active'], $this->actor($request));

        return response()->json(['message' => "User {$name} has been unbanned."]);
    }

    // GET /api/v1/admin/users/{name}/audit-trail
    public function auditTrail(string $name): JsonResponse
    {
        $history = DB::table('pascal_audit_logs')
            ->where('doctype', 'User')
            ->where('docname', $name)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $history]);
    }

    private function safe(array $user): array
    {
        unset($user['password'], $user['remember_token']);
        return $user;
    }
}
