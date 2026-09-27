<?php

namespace App\Models;

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

    public function deliveryNote()
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
