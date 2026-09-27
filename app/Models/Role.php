<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Builder;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'tenant_id',
    ];

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
