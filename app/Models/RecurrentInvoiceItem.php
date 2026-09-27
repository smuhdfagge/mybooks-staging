<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurrentInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'recurrent_invoice_id',
        'item_id',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'tax_rate',
        'tax_amount',
        'total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function recurrentInvoice()
    {
        return $this->belongsTo(RecurrentInvoice::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
