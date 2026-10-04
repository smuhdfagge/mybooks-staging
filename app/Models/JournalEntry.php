<?php

namespace App\Models;

use App\Services\Accounting\LockDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_id',
        'account_id',
        'description',
        'debit',
        'credit',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // A posted journal's lines can't be added, changed or removed in a
        // closed period or on or before a lock date (session 11). The journal
        // itself is checked by ValidatesAccountingPeriod, but a journal whose
        // lines were rebuilt with the same totals was never "dirty", so the
        // lines changed with no check.
        static::creating(fn (JournalEntry $entry) => $entry->guardLockedJournal());
        static::updating(fn (JournalEntry $entry) => $entry->guardLockedJournal());
        static::deleting(fn (JournalEntry $entry) => $entry->guardLockedJournal());
    }

    protected function guardLockedJournal(): void
    {
        $journal = Journal::withoutGlobalScopes()->find($this->journal_id);
        if (! $journal || ! $journal->is_posted) {
            return;
        }

        if ($reason = LockDates::instance()->blockReason($journal->journal_date, (int) $journal->tenant_id, auth()->user())) {
            throw ValidationException::withMessages(['journal_date' => [$reason]]);
        }
    }

    /** @return BelongsTo<Journal, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /** @return BelongsTo<ChartOfAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
