<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class SerialNumber extends Model
{
    use HasFactory, BelongsToTenant;

    const STATUS_AVAILABLE = 'available';
    const STATUS_RESERVED = 'reserved';
    const STATUS_SOLD = 'sold';
    const STATUS_RETURNED = 'returned';
    const STATUS_DAMAGED = 'damaged';

    protected $fillable = [
        'tenant_id',
        'item_id',
        'warehouse_id',
        'inventory_batch_id',
        'serial_number',
        'status',
        'reference_type',
        'reference_id',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function batch()
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }

    public function reference()
    {
        return $this->morphTo();
    }

    /**
     * Reserve this serial number for a transaction.
     */
    public function reserve(string $referenceType, int $referenceId): void
    {
        if ($this->status !== self::STATUS_AVAILABLE) {
            throw new \RuntimeException("Serial number {$this->serial_number} is not available.");
        }

        $this->update([
            'status' => self::STATUS_RESERVED,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Mark as sold.
     */
    public function markSold(string $referenceType, int $referenceId): void
    {
        $this->update([
            'status' => self::STATUS_SOLD,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Return to available stock.
     */
    public function returnToStock(): void
    {
        $this->update([
            'status' => self::STATUS_AVAILABLE,
            'reference_type' => null,
            'reference_id' => null,
        ]);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    public function scopeForItem($query, int $itemId, ?int $warehouseId = null)
    {
        $query->where('item_id', $itemId);

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query;
    }
}
