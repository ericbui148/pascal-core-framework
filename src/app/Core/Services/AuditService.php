<?php

namespace App\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditService
{
    public function log(
        string  $doctype,
        string  $docname,
        string  $action,
        ?array  $before,
        ?array  $after,
        mixed   $user,
        ?array  $diff = null,
    ): void {
        DB::table('pascal_audit_logs')->insert([
            'id'          => (string) Str::uuid(),
            'doctype'     => $doctype,
            'docname'     => $docname,
            'action'      => $action,
            'user_id'     => is_object($user) ? ($user->id ?? null) : ($user['id'] ?? null),
            'user_email'  => is_object($user) ? ($user->email ?? null) : ($user['email'] ?? null),
            'ip_address'  => request()->ip(),
            'before_data' => $before ? json_encode($before) : null,
            'after_data'  => $after  ? json_encode($after)  : null,
            'diff'        => $diff   ? json_encode($diff)   : null,
            'created_at'  => now(),
        ]);
    }

    public function history(string $doctype, string $docname, int $limit = 50): array
    {
        return DB::table('pascal_audit_logs')
            ->where('doctype', $doctype)
            ->where('docname', $docname)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
