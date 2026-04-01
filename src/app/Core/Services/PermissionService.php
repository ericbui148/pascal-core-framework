<?php

namespace App\Core\Services;

use Illuminate\Auth\Access\AuthorizationException;

class PermissionService
{
    // Action → minimum role required
    private const ROLE_HIERARCHY = ['user' => 1, 'manager' => 2, 'admin' => 3];

    private const DEFAULT_PERMISSIONS = [
        'read'   => 'user',
        'create' => 'user',
        'write'  => 'user',
        'delete' => 'manager',
        'submit' => 'manager',
        'cancel' => 'manager',
        'admin'  => 'admin',
    ];

    public function check(string $doctype, string $action, mixed $user): void
    {
        if (!$this->can($doctype, $action, $user)) {
            throw new AuthorizationException(
                "You do not have [{$action}] permission on [{$doctype}]."
            );
        }
    }

    public function can(string $doctype, string $action, mixed $user): bool
    {
        if (!$user) return false;

        $role      = is_object($user) ? ($user->role ?? 'user') : ($user['role'] ?? 'user');
        $userLevel = self::ROLE_HIERARCHY[$role] ?? 1;

        $requiredRole  = self::DEFAULT_PERMISSIONS[$action] ?? 'admin';
        $requiredLevel = self::ROLE_HIERARCHY[$requiredRole] ?? 3;

        return $userLevel >= $requiredLevel;
    }
}
