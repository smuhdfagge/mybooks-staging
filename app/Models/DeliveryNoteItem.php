<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryNoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_note_id',
        'sales_order_item_id',
        'item_id',
        'description',
        'quantity_ordered',
        'quantity_delivered',
    ];

    protected $casts = [
        'quantity_ordered' => 'decimal:2',
        'quantity_delivered' => 'decimal:2',
    ];

    /** @return BelongsTo<DeliveryNote, $this> */
    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    /** @return BelongsTo<SalesOrderItem, $this> */
    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
