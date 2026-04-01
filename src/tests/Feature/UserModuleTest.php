<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserModuleTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $overrides = []): array
    {
        $data = array_merge([
            'name'              => 'testuser',
            'docstatus'         => 0,
            'full_name'         => 'Test User',
            'email'             => 'test@example.com',
            'password'          => Hash::make('Password@123'),
            'role'              => 'user',
            'status'            => 'Active',
            'email_verified_at' => now(),
            'owner'             => 'system',
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $overrides);

        DB::table('pascal_users')->insert($data);
        return $data;
    }

    private function loginAs(array $user): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user['email'],
            'password' => 'Password@123',
        ]);
        return $response->json('token');
    }

    // ── Register ─────────────────────────────────────────────────────────────

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'full_name'             => 'Alice Smith',
            'email'                 => 'alice@example.com',
            'password'              => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'user', 'token'])
            ->assertJsonPath('user.email', 'alice@example.com');

        $this->assertDatabaseHas('pascal_users', ['email' => 'alice@example.com']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        $this->createUser(['email' => 'alice@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'full_name'             => 'Alice Again',
            'email'                 => 'alice@example.com',
            'password'              => 'Password@123',
            'password_confirmation' => 'Password@123',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_register_rejects_weak_password(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'full_name'             => 'Bob',
            'email'                 => 'bob@example.com',
            'password'              => 'weak',
            'password_confirmation' => 'weak',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    // ── Login ─────────────────────────────────────────────────────────────────

    public function test_user_can_login(): void
    {
        $this->createUser();

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'Password@123',
        ])->assertStatus(200)->assertJsonStructure(['token', 'expires_at', 'user']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->createUser();

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'WrongPass!',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_banned_user_cannot_login(): void
    {
        $this->createUser(['status' => 'Banned']);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'Password@123',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    // ── Me / Profile ──────────────────────────────────────────────────────────

    public function test_authenticated_user_gets_own_profile(): void
    {
        $user  = $this->createUser();
        $token = $this->loginAs($user);

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJsonPath('user.email', $user['email']);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function test_user_can_logout(): void
    {
        $user  = $this->createUser();
        $token = $this->loginAs($user);

        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200);

        // Token must now be invalid
        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(401);
    }

    // ── Profile Update ────────────────────────────────────────────────────────

    public function test_user_can_update_profile(): void
    {
        $user  = $this->createUser();
        $token = $this->loginAs($user);

        $this->putJson('/api/v1/user/profile',
            ['full_name' => 'Updated Name', 'phone' => '+1234567890'],
            ['Authorization' => "Bearer {$token}"]
        )->assertStatus(200)->assertJsonPath('data.full_name', 'Updated Name');
    }

    // ── Admin: User list ──────────────────────────────────────────────────────

    public function test_admin_can_list_users(): void
    {
        $admin = $this->createUser(['name' => 'admin-test', 'email' => 'admin@test.com', 'role' => 'admin']);
        $token = $this->loginAs($admin);

        $this->getJson('/api/v1/admin/users', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'total']);
    }

    public function test_regular_user_cannot_access_admin(): void
    {
        $user  = $this->createUser();
        $token = $this->loginAs($user);

        $this->getJson('/api/v1/admin/users', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(403);
    }

    // ── Admin: Ban ────────────────────────────────────────────────────────────

    public function test_admin_can_ban_user(): void
    {
        $admin  = $this->createUser(['name' => 'admin-2', 'email' => 'admin2@test.com', 'role' => 'admin']);
        $target = $this->createUser(['name' => 'target', 'email' => 'target@test.com']);
        $token  = $this->loginAs($admin);

        $this->postJson("/api/v1/admin/users/target/ban", [], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200);

        $this->assertDatabaseHas('pascal_users', ['name' => 'target', 'status' => 'Banned']);
    }

    // ── Audit Trail ───────────────────────────────────────────────────────────

    public function test_audit_log_is_written_on_create(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'full_name'             => 'Audit Test',
            'email'                 => 'audit@example.com',
            'password'              => 'Password@123',
            'password_confirmation' => 'Password@123',
        ])->assertStatus(201);

        $this->assertDatabaseHas('pascal_audit_logs', [
            'doctype' => 'User',
            'action'  => 'create',
        ]);
    }
}
