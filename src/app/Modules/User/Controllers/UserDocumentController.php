<?php

namespace App\Modules\User\Controllers;

use App\Core\DocType\BaseDocumentController;
use App\Modules\User\Events\UserBanned;
use App\Modules\User\Events\UserCreated;
use App\Modules\User\Events\UserStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * UserDocumentController — business logic for the "User" DocType.
 *
 * Called by DocumentService at the right lifecycle point.
 * Never called directly from a Controller or another module.
 */
class UserDocumentController extends BaseDocumentController
{
    // ── validate ─────────────────────────────────────────────────────────────

    public function validate(array &$data): void
    {
        $errors = [];

        if (empty($data['full_name'])) {
            $errors['full_name'] = ['Full name is required.'];
        }

        if (empty($data['email'])) {
            $errors['email'] = ['Email is required.'];
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ['Email format is invalid.'];
        }

        // Uniqueness checks (exclude current record on update)
        $name = $data['name'] ?? null;

        $emailTaken = DB::table('pascal_users')
            ->where('email', $data['email'])
            ->when($name, fn ($q) => $q->where('name', '!=', $name))
            ->exists();

        if ($emailTaken) {
            $errors['email'] = ['This email address is already in use.'];
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    // ── beforeSave ───────────────────────────────────────────────────────────

    public function beforeSave(array &$data, ?array $existing = null): void
    {
        // Hash password only if it was provided and changed
        if (!empty($data['password'])) {
            $currentHash = $existing['password'] ?? null;
            if (!$currentHash || !Hash::check($data['password'], $currentHash)) {
                $data['password'] = Hash::make($data['password']);
            }
        } elseif ($existing) {
            // Preserve existing hash on partial updates
            unset($data['password']);
        }

        // Default role
        if (empty($data['role'])) {
            $data['role'] = 'user';
        }

        // Default status
        if (empty($data['status'])) {
            $data['status'] = 'Active';
        }
    }

    // ── afterSave ────────────────────────────────────────────────────────────

    public function afterSave(array $data, string $action): void
    {
        if ($action === 'create') {
            Event::dispatch(new UserCreated($data));
        }
    }

    // ── beforeDelete ─────────────────────────────────────────────────────────

    public function beforeDelete(array $data): void
    {
        // Prevent deleting the last admin
        if (($data['role'] ?? '') === 'admin') {
            $adminCount = DB::table('pascal_users')
                ->where('role', 'admin')
                ->where('name', '!=', $data['name'])
                ->whereNull('deleted_at')
                ->count();

            if ($adminCount === 0) {
                throw new \LogicException('Cannot delete the last administrator account.');
            }
        }
    }
}
