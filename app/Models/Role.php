<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Exceptions\RoleAlreadyExists;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'tenant_id',
    ];

    /** System role names a tenant can't reuse for its own roles. */
    public const RESERVED_NAMES = ['admin', 'super-admin'];

    /**
     * Role names are unique per organisation, not across the platform
     * (finding H4). The database already enforces (tenant_id, name, guard);
     * Spatie's own check looked at every tenant, so two organisations could
     * not both have an "Accountant" role.
     */
    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] ??= Guard::getDefaultName(static::class);
        $tenantId = $attributes['tenant_id'] ?? null;

        $exists = static::query()
            ->where('name', $attributes['name'])
            ->where('guard_name', $attributes['guard_name'])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNull('tenant_id'))
            ->exists();

        if ($exists) {
            throw RoleAlreadyExists::create($attributes['name'], $attributes['guard_name']);
        }

        return static::query()->create($attributes);
    }

    /**
     * Looking a role up by name (e.g. assignRole('accountant')) only sees the
     * current organisation's roles and the shared system roles, preferring
     * the organisation's own. Without a signed-in user only system roles are
     * found, so another tenant's role can never be picked up by name.
     */
    protected static function findByParam(array $params = []): ?RoleContract
    {
        $tenantId = auth()->check() ? auth()->user()->tenant_id : null;

        $query = static::query()
            ->where(fn ($q) => $q->whereNull('tenant_id')->when($tenantId, fn ($q) => $q->orWhere('tenant_id', $tenantId)))
            ->orderByRaw('CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END');

        foreach ($params as $key => $value) {
            $query->where($key, $value);
        }

        return $query->first();
    }

    /**
     * NOTE: We do NOT use a global scope here because it interferes with
     * Spatie's permission checks. Instead, we filter roles in the UI/controller
     * using the scopeForCurrentTenant() method.
     */

    /**
     * Scope to get roles visible to the current tenant (tenant-specific only, excludes global roles).
     * Use this in controllers/views when listing roles for management.
     */
    public function scopeForCurrentTenant(Builder $query): Builder
    {
        if (auth()->check() && auth()->user()->tenant_id) {
            return $query->where('tenant_id', auth()->user()->tenant_id);
        }
        return $query;
    }

    /**
     * Scope to get roles for a specific tenant (includes global roles).
     */
    public function scopeForTenant(Builder $query, $tenantId): Builder
    {
        return $query->where(function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId)
              ->orWhereNull('tenant_id');
        });
    }

    /**
     * Scope to get only tenant-specific roles (exclude global roles).
     */
    public function scopeTenantOnly(Builder $query, $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope to get only global roles.
     */
    public function scopeGlobalOnly(Builder $query): Builder
    {
        return $query->whereNull('tenant_id');
    }

    /**
     * Get the tenant that owns the role.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Check if this is a global role.
     */
    public function isGlobal(): bool
    {
        return is_null($this->tenant_id);
    }

    /**
     * Check if this role belongs to a specific tenant.
     */
    public function belongsToTenant($tenantId): bool
    {
        return $this->tenant_id === $tenantId;
    }
}
