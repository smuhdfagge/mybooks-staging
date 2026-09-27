<?php

namespace App\Models;

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

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function inventoryLayers()
    {
        return $this->hasMany(InventoryLayer::class);
    }

    public function incomingTransfers()
    {
        return $this->hasMany(StockTransfer::class, 'to_warehouse_id');
    }

    public function outgoingTransfers()
    {
        return $this->hasMany(StockTransfer::class, 'from_warehouse_id');
    }

    public function batches()
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function serialNumbers()
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
