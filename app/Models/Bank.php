<?php

namespace App\Models;

use App\Casts\SafeEncrypted;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Bank extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'chart_of_account_id',
        'name',
        'bank_name',
        'account_number',
        'account_type',
        'currency',
        'routing_number',
        'swift_code',
        'iban',
        'branch_name',
        'branch_address',
        'opening_balance',
        'current_balance',
        'opening_balance_date',
        'description',
        'is_primary',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'opening_balance_date' => 'date',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'account_number' => SafeEncrypted::class,
        'routing_number' => SafeEncrypted::class,
        'swift_code' => SafeEncrypted::class,
        'iban' => SafeEncrypted::class,
    ];

    // Account Types
    const TYPE_CHECKING = 'checking';

    const TYPE_SAVINGS = 'savings';

    const TYPE_CREDIT_CARD = 'credit_card';

    const TYPE_CASH = 'cash';

    const TYPE_OTHER = 'other';

    public static function getAccountTypes(): array
    {
        return [
            self::TYPE_CHECKING => 'Checking Account',
            self::TYPE_SAVINGS => 'Savings Account',
            self::TYPE_CREDIT_CARD => 'Credit Card',
            self::TYPE_CASH => 'Cash',
            self::TYPE_OTHER => 'Other',
        ];
    }

    /** @return BelongsTo<ChartOfAccount, $this> */
    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    /** @return HasMany<BankTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    /**
     * Why this account can't be deleted, or null if it can: anything recorded
     * through it (bank lines, payments in or out, expenses) keeps it.
     */
    public function deleteBlockedReason(): ?string
    {
        $used = $this->transactions()->exists()
            || PaymentReceived::where('bank_id', $this->id)->exists()
            || PaymentMade::where('bank_id', $this->id)->exists()
            || Expense::where('bank_id', $this->id)->exists();

        return $used ? "{$this->name} has money recorded through it, so it can't be deleted. Make it inactive instead." : null;
    }

    public function getMaskedAccountNumberAttribute(): string
    {
        if (empty($this->account_number)) {
            return '';
        }

        $length = strlen($this->account_number);
        if ($length <= 4) {
            return $this->account_number;
        }

        return str_repeat('•', $length - 4).substr($this->account_number, -4);
    }

    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->current_balance, 2);
    }

    public function updateBalance(): void
    {
        $deposits = $this->transactions()
            ->whereIn('type', ['deposit', 'interest'])
            ->sum('amount');

        $withdrawals = $this->transactions()
            ->whereIn('type', ['withdrawal', 'fee', 'transfer_out'])
            ->sum('amount');

        $this->current_balance = $this->opening_balance + $deposits - $withdrawals;
        $this->save();
    }

    /**
     * Adds `ledger_balance` (audit MB-08): the opening balance plus every
     * posted movement on the bank's ledger account, as the bank's "Money in
     * and out" page counts it. current_balance was a counter only payments
     * and expenses moved, so manual journals, payroll, sales receipts and
     * refunds never reached it. A bank with no ledger account keeps the
     * counter.
     */
    public function scopeWithLedgerBalance(Builder $query, ?string $asOf = null): Builder
    {
        return static::addLedgerBalance($query, $asOf);
    }

    /**
     * scopeWithLedgerBalance for a query typed only as a Builder (list tables).
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function addLedgerBalance(Builder $query, ?string $asOf = null): Builder
    {
        $movements = DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->whereColumn('je.account_id', 'banks.chart_of_account_id')
            ->whereColumn('j.tenant_id', 'banks.tenant_id')
            ->where('j.is_posted', true)
            ->whereNull('j.deleted_at')
            // "Before the next day": SQLite keeps a time part on dates.
            ->when($asOf, fn ($q) => $q->where('j.journal_date', '<', Carbon::parse($asOf)->addDay()->toDateString()))
            ->selectRaw('COALESCE(SUM(je.debit), 0) - COALESCE(SUM(je.credit), 0)');

        if ($query->getQuery()->columns === null) {
            $query->select('banks.*');
        }

        return $query->selectRaw(
            'CASE WHEN banks.chart_of_account_id IS NULL THEN banks.current_balance ELSE COALESCE(banks.opening_balance, 0) + ('.$movements->toSql().') END as ledger_balance',
            $movements->getBindings()
        );
    }

    /** The balance the books show for this bank (see scopeWithLedgerBalance). */
    public function ledgerBalance(?string $asOf = null): float
    {
        if (! $this->chart_of_account_id) {
            return round((float) $this->current_balance, 2);
        }

        return round((float) static::addLedgerBalance(static::withoutGlobalScope('tenant')->whereKey($this->getKey()), $asOf)->value('ledger_balance'), 2);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}
