<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'inventories';

    protected $fillable = [
        'tenant_id',
        'item_id',
        'warehouse_id',
        'quantity',
        'reserved_quantity',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'reserved_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:2',
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

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function getAvailableQuantityAttribute()
    {
        return $this->quantity - $this->reserved_quantity;
    }
}
