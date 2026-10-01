<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Pension Fund Administrator. Rows with no tenant are the PenCom list
 * every business sees; a business may add its own (tenant_id set).
 */
class PensionFundAdministrator extends Model
{
    protected $fillable = ['tenant_id', 'name', 'code', 'is_active', 'source'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** The shared list plus the business's own PFAs. */
    public function scopeAvailableTo(Builder $query, int $tenantId): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId));
    }

    public function isShared(): bool
    {
        return $this->tenant_id === null;
    }

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
