<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

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

    /** @return BelongsTo<InventoryLayer, $this> */
    public function layer(): BelongsTo
    {
        return $this->belongsTo(InventoryLayer::class, 'inventory_layer_id');
    }
}
