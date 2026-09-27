<?php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    /**
     * Per-instance flag to skip the tenant guard on creating.
     */
    public bool $skipTenantGuard = false;

    protected static function bootBelongsToTenant()
    {
        // Auto-assign tenant_id on creation; throw if missing outside auth context
        static::creating(function ($model) {
            if ($model->skipTenantGuard || static::isTenantGuardDisabled()) {
                return;
            }

            if (auth()->check() && auth()->user()->tenant_id) {
                $model->tenant_id = auth()->user()->tenant_id;
            }

            // Ensure tenant_id is present before persisting
            if (empty($model->tenant_id)) {
                throw new \RuntimeException(
                    'Cannot create ' . class_basename($model) . ' without a tenant_id. '
                    . 'Set tenant_id explicitly or authenticate a user with a tenant.'
                );
            }
        });

        // Prevent tenant_id from being changed on existing records
        static::updating(function ($model) {
            if ($model->isDirty('tenant_id') && $model->getOriginal('tenant_id') !== null) {
                throw new \RuntimeException(
                    'Cannot change tenant_id on an existing ' . class_basename($model) . '.'
                );
            }
        });

        // Qualify column with table name to avoid ambiguity in joins
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (auth()->check() && auth()->user()?->tenant_id) {
                $builder->where(
                    $builder->getModel()->getTable() . '.tenant_id',
                    auth()->user()->tenant_id
                );
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Check if the tenant guard is currently disabled (request-scoped).
     * Uses the container instead of a static property so state cannot
     * leak across requests in long-lived processes (Octane / queue workers).
     */
    protected static function isTenantGuardDisabled(): bool
    {
        return app()->bound('tenant.guard.disabled')
            && app('tenant.guard.disabled') === true;
    }

    /**
     * Execute a callback with the tenant guard disabled for ALL models.
     * Uses a request-scoped container binding so state is automatically
     * flushed between requests in Octane and queue workers.
     *
     * Caller is responsible for setting tenant_id on created models.
     *
     * Example:
     *   Bank::withoutTenantGuard(fn () => Bank::create([...,'tenant_id' => $id]));
     */
    public static function withoutTenantGuard(callable $callback): mixed
    {
        app()->instance('tenant.guard.disabled', true);

        try {
            return $callback();
        } finally {
            app()->forgetInstance('tenant.guard.disabled');
        }
    }
}
