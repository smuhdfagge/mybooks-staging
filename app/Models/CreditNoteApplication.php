<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditNoteApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_note_id',
        'invoice_id',
        'amount',
        'applied_date',
        'applied_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'applied_date' => 'date',
    ];

    public function creditNote()
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function appliedBy()
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
