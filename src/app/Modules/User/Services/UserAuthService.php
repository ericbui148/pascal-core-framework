<?php

namespace App\Modules\User\Services;

use App\Core\DocType\DocTypeRegistry;
use App\Core\Services\DocumentService;
use App\Modules\User\Events\UserLoggedIn;
use App\Modules\User\Events\UserLoggedOut;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserAuthService
{
    public function __construct(protected DocumentService $documents) {}

    // ── Register ─────────────────────────────────────────────────────────────

    public function register(array $data): array
    {
        // Validate password confirmation before handing off to DocumentController
        if (($data['password'] ?? '') !== ($data['password_confirmation'] ?? '')) {
            throw ValidationException::withMessages([
                'password' => ['Password confirmation does not match.'],
            ]);
        }

        $this->validatePasswordStrength($data['password'] ?? '');

        // Build a system actor for the audit log (no authenticated user yet)
        $system = (object) ['id' => null, 'email' => 'system'];

        $user = $this->documents->create('User', [
            'name'      => $data['name'] ?? Str::slug($data['email']),
            'full_name' => $data['full_name'],
            'email'     => $data['email'],
            'password'  => $data['password'],
            'role'      => 'user',
            'status'    => 'Active',
        ], $system);

        // Mark email as unverified — send verification mail
        event(new Registered($this->eloquentUser($user['name'])));

        $token = $this->issueToken($user['name'], 'auth_token', now()->addDays(30));

        return ['user' => $this->publicFields($user), 'token' => $token];
    }

    // ── Login ─────────────────────────────────────────────────────────────────

    public function login(array $credentials, string $ip, string $ua): array
    {
        $row = DB::table('pascal_users')
            ->where('email', $credentials['email'])
            ->whereNull('deleted_at')
            ->first();

        if (!$row || !Hash::check($credentials['password'], $row->password ?? '')) {
            $this->recordFailedLogin($credentials['email'], $ip, $ua);
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($row->status === 'Banned') {
            throw ValidationException::withMessages([
                'email' => ['Your account has been banned. Contact support.'],
            ]);
        }

        if ($row->status === 'Inactive') {
            throw ValidationException::withMessages([
                'email' => ['Your account is inactive. Contact support.'],
            ]);
        }

        $rememberMe = !empty($credentials['remember_me']);
        $expiry     = $rememberMe ? now()->addDays(30) : now()->addHours(8);

        // Revoke old tokens unless "remember me"
        if (!$rememberMe) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', 'pascal_user')
                ->where('tokenable_id', $row->id)
                ->delete();
        }

        $token = $this->issueToken($row->name, 'auth_token', $expiry);

        // Update last login
        DB::table('pascal_users')->where('name', $row->name)->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
            'updated_at'    => now(),
        ]);

        $this->recordLoginHistory($row->name, $ip, $ua, 'success');
        Event::dispatch(new UserLoggedIn((array) $row, $ip, $ua));

        $user = (array) DB::table('pascal_users')->where('name', $row->name)->first();

        return [
            'user'       => $this->publicFields($user),
            'token'      => $token,
            'expires_at' => $expiry->toIso8601String(),
        ];
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function logout(string $tokenId, array $user): void
    {
        DB::table('personal_access_tokens')->where('id', $tokenId)->delete();
        Event::dispatch(new UserLoggedOut($user));
    }

    public function logoutAllDevices(int $userId): void
    {
        DB::table('personal_access_tokens')
            ->where('tokenable_type', 'pascal_user')
            ->where('tokenable_id', $userId)
            ->delete();
    }

    // ── Forgot Password ───────────────────────────────────────────────────────

    public function forgotPassword(string $email): void
    {
        // Always return success to prevent email enumeration
        $user = DB::table('pascal_users')->where('email', $email)->first();
        if (!$user) return;

        $token = Str::random(64);

        DB::table('password_reset_tokens')->upsert(
            ['email' => $email, 'token' => Hash::make($token), 'created_at' => now()],
            ['email'],
            ['token', 'created_at'],
        );

        // Send email (queued)
        \Illuminate\Support\Facades\Mail::to($email)->queue(
            new \App\Modules\User\Mail\PasswordResetMail($token, $email)
        );
    }

    // ── Reset Password ────────────────────────────────────────────────────────

    public function resetPassword(array $data): void
    {
        $record = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        if (!$record || !Hash::check($data['token'], $record->token ?? '')) {
            throw ValidationException::withMessages([
                'token' => ['This password reset token is invalid or expired.'],
            ]);
        }

        // Token expires after 60 minutes
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
            throw ValidationException::withMessages([
                'token' => ['This password reset token has expired.'],
            ]);
        }

        $this->validatePasswordStrength($data['password']);

        if ($data['password'] !== $data['password_confirmation']) {
            throw ValidationException::withMessages([
                'password' => ['Password confirmation does not match.'],
            ]);
        }

        DB::table('pascal_users')->where('email', $data['email'])->update([
            'password'   => Hash::make($data['password']),
            'updated_at' => now(),
        ]);

        // Revoke all sessions
        $user = DB::table('pascal_users')->where('email', $data['email'])->first();
        if ($user) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', 'pascal_user')
                ->where('tokenable_id', $user->id)
                ->delete();
        }

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
    }

    // ── Change Password ───────────────────────────────────────────────────────

    public function changePassword(array $user, string $current, string $new, int $currentTokenId): void
    {
        if (!Hash::check($current, $user['password'] ?? '')) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $this->validatePasswordStrength($new);

        DB::table('pascal_users')->where('name', $user['name'])->update([
            'password'   => Hash::make($new),
            'updated_at' => now(),
        ]);

        // Revoke all other sessions
        DB::table('personal_access_tokens')
            ->where('tokenable_type', 'pascal_user')
            ->where('tokenable_id', $user['id'])
            ->where('id', '!=', $currentTokenId)
            ->delete();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function publicFields(array $user): array
    {
        unset($user['password'], $user['remember_token']);
        return $user;
    }

    public function getUserByToken(string $rawToken): ?array
    {
        [$id, $token] = explode('|', $rawToken, 2) + [null, null];

        $pat = DB::table('personal_access_tokens')->where('id', $id)->first();

        if (!$pat || !Hash::check($token, $pat->token)) {
            return null;
        }

        if ($pat->expires_at && now()->isAfter($pat->expires_at)) {
            return null;
        }

        // Update last_used_at
        DB::table('personal_access_tokens')->where('id', $id)->update(['last_used_at' => now()]);

        $user = DB::table('pascal_users')
            ->where('id', $pat->tokenable_id)
            ->whereNull('deleted_at')
            ->first();

        if (!$user) return null;

        $result           = (array) $user;
        $result['_token_id'] = (int) $pat->id;

        return $result;
    }

    private function issueToken(string $userName, string $name, \Carbon\Carbon $expiresAt): string
    {
        $user = DB::table('pascal_users')->where('name', $userName)->first();

        $plainToken = Str::random(40);
        $id = DB::table('personal_access_tokens')->insertGetId([
            'tokenable_type' => 'pascal_user',
            'tokenable_id'   => $user->id,
            'name'           => $name,
            'token'          => hash('sha256', $plainToken),
            'abilities'      => '["*"]',
            'expires_at'     => $expiresAt,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return "{$id}|{$plainToken}";
    }

    private function eloquentUser(string $name): object
    {
        // Minimal object for Laravel's Registered event
        $row = DB::table('pascal_users')->where('name', $name)->first();
        return new class($row) implements \Illuminate\Contracts\Auth\MustVerifyEmail {
            public int    $id;
            public string $email;
            public ?string $email_verified_at;

            public function __construct(object $row) {
                $this->id                = $row->id;
                $this->email             = $row->email;
                $this->email_verified_at = $row->email_verified_at ?? null;
            }

            public function hasVerifiedEmail(): bool   { return !is_null($this->email_verified_at); }
            public function markEmailAsVerified(): bool { return true; }
            public function sendEmailVerificationNotification(): void {}
            public function getEmailForVerification(): string { return $this->email; }
        };
    }

    private function validatePasswordStrength(string $password): void
    {
        if (strlen($password) < 8) {
            throw ValidationException::withMessages([
                'password' => ['Password must be at least 8 characters.'],
            ]);
        }
        if (!preg_match('/[A-Z]/', $password)) {
            throw ValidationException::withMessages([
                'password' => ['Password must contain at least one uppercase letter.'],
            ]);
        }
        if (!preg_match('/[a-z]/', $password)) {
            throw ValidationException::withMessages([
                'password' => ['Password must contain at least one lowercase letter.'],
            ]);
        }
        if (!preg_match('/[0-9]/', $password)) {
            throw ValidationException::withMessages([
                'password' => ['Password must contain at least one number.'],
            ]);
        }
    }

    private function recordLoginHistory(string $userName, string $ip, string $ua, string $status): void
    {
        $user = DB::table('pascal_users')->where('name', $userName)->first();

        DB::table('pascal_login_histories')->insert([
            'user_id'      => $user?->id,
            'ip_address'   => $ip,
            'user_agent'   => $ua,
            'status'       => $status,
            'logged_in_at' => now(),
        ]);
    }

    private function recordFailedLogin(string $email, string $ip, string $ua): void
    {
        DB::table('pascal_login_histories')->insert([
            'user_id'        => null,
            'ip_address'     => $ip,
            'user_agent'     => $ua,
            'status'         => 'failed',
            'failure_reason' => "Failed login attempt for {$email}",
            'logged_in_at'   => now(),
        ]);
    }
}
