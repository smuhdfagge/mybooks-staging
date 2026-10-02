<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment against a statutory schedule group for a pay month, e.g. PAYE
 * to Kano IRS for September 2026, with its journal.
 */
class StatutoryRemittance extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'schedule', 'period', 'group_key', 'group_label', 'amount', 'paid_on',
        'bank_id', 'payment_method', 'reference', 'journal_id', 'created_by',
    ];

    protected $casts = [
        'period' => 'date',
        'paid_on' => 'date',
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<Journal, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /** @return BelongsTo<Bank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
}
