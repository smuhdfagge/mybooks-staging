<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a bank statement imported from CSV (audit MB-08), matched to
 * at most one line from the books. Done once that line is reconciled.
 */
class BankStatementLine extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'bank_id', 'line_hash', 'date', 'description', 'reference', 'amount', 'balance', 'bank_transaction_id', 'imported_by',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    /** @return BelongsTo<Bank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /** @return BelongsTo<BankTransaction, $this> */
    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    public function isInflow(): bool
    {
        return (float) $this->amount > 0;
    }
}
