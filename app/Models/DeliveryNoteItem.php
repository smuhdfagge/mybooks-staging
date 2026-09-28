<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryNoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_note_id',
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

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
