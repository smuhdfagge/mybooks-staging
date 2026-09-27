<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class InventoryBatch extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'item_id',
        'warehouse_id',
        'batch_number',
        'quantity',
        'remaining_quantity',
        'manufacture_date',
        'expiry_date',
        'reference_type',
        'reference_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'remaining_quantity' => 'decimal:4',
        'manufacture_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function serialNumbers()
    {
        return $this->hasMany(SerialNumber::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    /**
     * Check if this batch is expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /**
     * Consume quantity from this batch. Returns amount consumed.
     */
    public function consume(float $quantity): float
    {
        $consumed = min($quantity, (float) $this->remaining_quantity);
        $this->remaining_quantity -= $consumed;
        $this->save();

        return $consumed;
    }

    /**
     * Scope to get batches with remaining stock, FEFO (First Expired, First Out).
     */
    public function scopeWithStock($query)
    {
        return $query->where('remaining_quantity', '>', 0)
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->orderBy('id');
    }

    /**
     * Scope to filter by item and optionally warehouse.
     */
    public function scopeForItem($query, int $itemId, ?int $warehouseId = null)
    {
        $query->where('item_id', $itemId);

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query;
    }
}
