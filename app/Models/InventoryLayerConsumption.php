<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock a sales document took from a cost layer, so the same stock can be
 * put back exactly if the document is edited, cancelled or deleted.
 */
class InventoryLayerConsumption extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'item_id',
        'warehouse_id',
        'inventory_layer_id',
        'source_type',
        'source_id',
        'quantity',
        'unit_cost',
        'reduced_on_hand',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'reduced_on_hand' => 'boolean',
    ];

    /** Stock with no warehouse given goes into the business's default one (session 12). */
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->warehouse_id) && $model->tenant_id) {
                $model->warehouse_id = Warehouse::defaultIdFor((int) $model->tenant_id);
            }
        });
    }

    /** @return BelongsTo<InventoryLayer, $this> */
    public function layer(): BelongsTo
    {
        return $this->belongsTo(InventoryLayer::class, 'inventory_layer_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
