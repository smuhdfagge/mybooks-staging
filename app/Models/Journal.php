<?php

namespace App\Models;

use App\Exceptions\BusinessRuleException;
use App\Services\JournalService;
use App\Traits\BelongsToTenant;
use App\Traits\HasDocumentNumber;
use App\Traits\LogsActivity;
use App\Traits\ValidatesAccountingPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Journal extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes, ValidatesAccountingPeriod;
    use HasDocumentNumber;

    /** Year-end closing journal: kept out of the profit and loss (A8). */
    public const TYPE_CLOSING = 'closing';

    /** The automatic reversal of a journal with a "reverse on" date (accruals). */
    public const TYPE_AUTO_REVERSAL = 'auto_reversal';

    protected $fillable = [
        'tenant_id',
        'journal_number',
        'journal_date',
        'reverse_on',
        'reference',
        'description',
        'total_debit',
        'total_credit',
        'status',
        'is_posted',
        'posted_at',
        'reference_type',
        'reference_id',
        'journal_type',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'journal_date' => 'date',
        'reverse_on' => 'date',
        'posted_at' => 'datetime',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'is_posted' => 'boolean',
    ];

    /** @return HasMany<JournalEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * The journal posted automatically on the "reverse on" date.
     *
     * @return BelongsTo<Journal, $this>
     */
    public function autoReversal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'auto_reversal_journal_id');
    }

    /**
     * For an automatic reversal: the journal it reverses.
     *
     * @return BelongsTo<Journal, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'reference_id');
    }

    public function isAutoReversal(): bool
    {
        return $this->journal_type === self::TYPE_AUTO_REVERSAL;
    }

    /** Has a "reverse on" date whose reversal isn't posted yet (and wasn't cancelled). */
    public function hasPendingReversal(): bool
    {
        return $this->reverse_on !== null && ! $this->auto_reversal_journal_id
            && in_array($this->status, ['draft', 'pending', 'posted'], true);
    }

    /**
     * Why this journal can't be reversed or voided by hand, or null. An
     * automatic reversal belongs to its original, and an original already
     * reversed automatically has nothing left to undo (a second reversal
     * would count the accrual back twice).
     */
    public function manualReversalBlockedReason(): ?string
    {
        if ($this->isAutoReversal()) {
            return "{$this->journal_number} is an automatic reversal and can't be reversed or voided by itself.";
        }
        if ($this->auto_reversal_journal_id) {
            $number = $this->autoReversal()->withoutGlobalScope('tenant')->value('journal_number');

            return "{$this->journal_number} was already reversed automatically by {$number}, so there is nothing left to reverse.";
        }

        return null;
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return array{0: string, 1: string, 2: int} */
    protected static function documentNumberFormat(): array
    {
        return ['journal_number', 'JE-', 6];
    }

    public function isBalanced(): bool
    {
        // Check the lines themselves. total_debit/total_credit are only
        // filled in by updateTotals(), so a draft journal read 0 = 0 and
        // counted as balanced whatever its lines said.
        $debit = round((float) $this->entries()->sum('debit'), 2);
        $credit = round((float) $this->entries()->sum('credit'), 2);

        return abs($debit - $credit) < 0.005;
    }

    public function updateTotals()
    {
        $this->total_debit = $this->entries()->sum('debit');
        $this->total_credit = $this->entries()->sum('credit');
        $this->withoutPeriodValidation()->save();
    }

    public function post()
    {
        if (! $this->entries()->exists()) {
            throw new BusinessRuleException('Journal has no lines to post.');
        }

        if (! $this->isBalanced()) {
            throw new BusinessRuleException('Journal entries must be balanced before posting.');
        }

        foreach ($this->entries as $entry) {
            $account = $entry->account;
            if ($account->isDebitBalance()) {
                $delta = (float) ($entry->debit - $entry->credit);
            } else {
                $delta = (float) ($entry->credit - $entry->debit);
            }
            ChartOfAccount::where('id', $account->id)
                ->update(['current_balance' => \DB::raw('current_balance + ('.(float) $delta.')')]);
        }

        $this->is_posted = true;
        $this->posted_at = now();
        $this->status = 'posted';
        $this->withoutPeriodValidation()->save();

        // Log the posting activity
        $this->logCustomActivity(ActivityLog::ACTION_POSTED, "Journal '{$this->journal_number}' was posted");

        // Catch-up: a journal posted with a "reverse on" date that has already
        // come (a back-dated accrual) is reversed now, not at the next daily
        // run. If that fails the daily command tries again.
        if ($this->reverse_on && $this->reverse_on->lte(today())) {
            try {
                app(JournalService::class)->postAutoReversal($this);
                $this->refresh();
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
