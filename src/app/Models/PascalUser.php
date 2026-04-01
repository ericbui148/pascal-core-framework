<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * PascalUser — Eloquent model that wraps pascal_users for Filament authentication.
 *
 * We keep our custom pascal_users table (DocType-aligned) and just add
 * the Filament interface on top. No Sanctum — Filament uses session auth.
 */
class PascalUser extends Authenticatable implements FilamentUser
{
    use Notifiable, SoftDeletes;

    protected $table      = 'pascal_users';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name', 'full_name', 'email', 'password',
        'role', 'status', 'avatar', 'phone',
        'email_verified_at', 'last_login_at', 'last_login_ip',
        'owner',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'password'          => 'hashed',
    ];

    // ── Filament interface ────────────────────────────────────────────────────

    /**
     * Only Active users with manager or admin role can access the panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status === 'Active'
            && in_array($this->role, ['admin', 'manager']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getFilamentName(): string
    {
        return $this->full_name;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar
            ? asset('storage/' . $this->avatar)
            : null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['admin', 'manager']);
    }
}
