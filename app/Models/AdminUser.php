<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class AdminUser extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'admin_users';

    // Admin roles from most to least privileged
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_ADMIN = 'admin';
    const ROLE_VIEWER = 'viewer';

    const ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ADMIN,
        self::ROLE_VIEWER,
    ];

    /**
     * Maps each role to the abilities it grants.
     * Abilities are checked by the AdminRole middleware.
     */
    const ROLE_ABILITIES = [
        self::ROLE_SUPER_ADMIN => [
            'manage-tenants',
            'manage-subscriptions',
            'manage-admin-users',
            'view-dashboard',
        ],
        self::ROLE_ADMIN => [
            'manage-tenants',
            'manage-subscriptions',
            'view-dashboard',
        ],
        self::ROLE_VIEWER => [
            'view-dashboard',
        ],
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Check if admin is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if admin has a specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if admin has a specific ability
     */
    public function hasAbility(string $ability): bool
    {
        $abilities = self::ROLE_ABILITIES[$this->role] ?? [];
        return in_array($ability, $abilities);
    }

    /**
     * Check if admin is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
