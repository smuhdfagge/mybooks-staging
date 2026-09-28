<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class UomConversion extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'item_id',
        'from_uom_id',
        'to_uom_id',
        'conversion_factor',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:6',
    ];

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function fromUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'from_uom_id');
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function toUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'to_uom_id');
    }

    /**
     * Convert a quantity from one UOM to another.
     */
    public function convert(float $quantity): float
    {
        return $quantity * (float) $this->conversion_factor;
    }

    /**
     * Find the conversion factor between two UOMs (optionally item-specific).
     */
    public static function findFactor(int $tenantId, int $fromUomId, int $toUomId, ?int $itemId = null): ?float
    {
        if ($fromUomId === $toUomId) {
            return 1.0;
        }

        // Try item-specific first
        if ($itemId) {
            $conversion = static::where('tenant_id', $tenantId)
                ->where('item_id', $itemId)
                ->where('from_uom_id', $fromUomId)
                ->where('to_uom_id', $toUomId)
                ->first();

            if ($conversion) {
                return (float) $conversion->conversion_factor;
            }
        }

        // Fall back to global conversion
        $conversion = static::where('tenant_id', $tenantId)
            ->whereNull('item_id')
            ->where('from_uom_id', $fromUomId)
            ->where('to_uom_id', $toUomId)
            ->first();

        if ($conversion) {
            return (float) $conversion->conversion_factor;
        }

        // Try reverse direction
        $reverse = static::where('tenant_id', $tenantId)
            ->where(function ($q) use ($itemId) {
                $q->where('item_id', $itemId)->orWhereNull('item_id');
            })
            ->where('from_uom_id', $toUomId)
            ->where('to_uom_id', $fromUomId)
            ->first();

        if ($reverse && (float) $reverse->conversion_factor > 0) {
            return 1.0 / (float) $reverse->conversion_factor;
        }

        return null;
    }
}
