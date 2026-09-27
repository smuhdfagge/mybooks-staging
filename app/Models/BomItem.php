<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BomItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_of_materials_id',
        'item_id',
        'quantity',
        'waste_percentage',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'waste_percentage' => 'decimal:2',
    ];

    public function billOfMaterial()
    {
        return $this->belongsTo(BillOfMaterial::class, 'bill_of_materials_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the effective quantity including waste.
     */
    public function getEffectiveQuantityAttribute(): float
    {
        return (float) $this->quantity * (1 + ((float) $this->waste_percentage / 100));
    }
}
