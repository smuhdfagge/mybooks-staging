<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;

class Warehouse extends Model
{
    use HasFactory, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'address',
        'contact_person',
        'phone',
        'email',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<Inventory, $this> */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /** @return HasMany<InventoryLayer, $this> */
    public function inventoryLayers(): HasMany
    {
        return $this->hasMany(InventoryLayer::class);
    }

    /** @return HasMany<StockTransfer, $this> */
    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'to_warehouse_id');
    }

    /** @return HasMany<StockTransfer, $this> */
    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'from_warehouse_id');
    }

    /** @return HasMany<InventoryBatch, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    /** @return HasMany<SerialNumber, $this> */
    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the default warehouse for a tenant.
     */
    public static function getDefault(int $tenantId): ?self
    {
        return static::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first();
    }

    /**
     * Set this warehouse as the default (unsets others).
     */
    public function setAsDefault(): void
    {
        static::where('tenant_id', $this->tenant_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Get total stock value across all items in this warehouse.
     */
    public function getTotalStockValueAttribute(): float
    {
        return $this->inventories()
            ->selectRaw('SUM(quantity * unit_cost) as total')
            ->value('total') ?? 0;
    }
}
