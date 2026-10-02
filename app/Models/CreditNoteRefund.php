<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money paid back to a customer out of an open credit note.
 */
class CreditNoteRefund extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'credit_note_id',
        'refund_date',
        'amount',
        'payment_method',
        'bank_id',
        'reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'refund_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<CreditNote, $this> */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /** @return BelongsTo<Bank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
}
