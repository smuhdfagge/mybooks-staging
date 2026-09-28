<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class UnitOfMeasure extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'unit_of_measures';

    protected $fillable = [
        'tenant_id',
        'name',
        'abbreviation',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** @return HasMany<UomConversion, $this> */
    public function conversionsFrom(): HasMany
    {
        return $this->hasMany(UomConversion::class, 'from_uom_id');
    }

    /** @return HasMany<UomConversion, $this> */
    public function conversionsTo(): HasMany
    {
        return $this->hasMany(UomConversion::class, 'to_uom_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
