<?php

namespace App\Models;

use App\Enums\AccrualScheduleStatus;
use App\Support\Money;
use App\Traits\BelongsToTenant;
use App\Traits\GuardsStatusTransitions;
use App\Traits\HasDocumentNumber;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A prepaid expense (e.g. a year's rent paid up front, sitting in Prepaid
 * Expenses) or deferred revenue (a customer paid up front for a service,
 * sitting in Deferred Revenue), moved to the expense or income account one
 * month at a time (S9). The schedule doesn't post the original payment:
 * that is the bill, expense, invoice or journal that put the money in the
 * balance-sheet account.
 *
 * Month n is released on the last day of the n-th month from the start
 * month. Each month gets the total shared equally in whole kobo; the last
 * month takes the rounding difference so the months add up exactly.
 */
class AccrualSchedule extends Model
{
    use BelongsToTenant, GuardsStatusTransitions, HasDocumentNumber;

    public const TYPE_PREPAID = 'prepaid_expense';

    public const TYPE_DEFERRED = 'deferred_revenue';

    public const TYPES = [
        self::TYPE_PREPAID => 'Prepaid expense',
        self::TYPE_DEFERRED => 'Deferred revenue',
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const MAX_MONTHS = 120;

    /** Documents a schedule can point to: type => [model, number column, route]. */
    public const SOURCES = [
        'bill' => [Bill::class, 'bill_number', 'bills.show'],
        'expense' => [Expense::class, 'expense_number', 'expenses.show'],
        'invoice' => [Invoice::class, 'invoice_number', 'invoices.show'],
    ];

    protected $fillable = [
        'tenant_id', 'schedule_number', 'type', 'description', 'total_amount', 'start_date', 'months',
        'balance_account_id', 'pl_account_id', 'source_type', 'source_id', 'reference', 'notes',
        'status', 'released_amount', 'created_by', 'cancelled_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'released_amount' => 'decimal:2',
        'start_date' => 'date',
        'months' => 'integer',
        'cancelled_at' => 'datetime',
    ];

    protected static function statusEnum(): string
    {
        return AccrualScheduleStatus::class;
    }

    protected static function statusDocumentName(): string
    {
        return 'schedule';
    }

    /** @return BelongsTo<ChartOfAccount, $this> */
    public function balanceAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'balance_account_id');
    }

    /** @return BelongsTo<ChartOfAccount, $this> */
    public function plAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'pl_account_id');
    }

    /** @return HasMany<AccrualScheduleRelease, $this> */
    public function releases(): HasMany
    {
        return $this->hasMany(AccrualScheduleRelease::class)->orderBy('sequence');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPrepaid(): bool
    {
        return $this->type === self::TYPE_PREPAID;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** The bill, expense or invoice it was linked to, if any (same business). */
    public function sourceDocument(): ?Model
    {
        $source = self::SOURCES[$this->source_type] ?? null;
        if (! $source || ! $this->source_id) {
            return null;
        }

        return $source[0]::withoutGlobalScope('tenant')->where('tenant_id', $this->tenant_id)->find($this->source_id);
    }

    /**
     * Each month's amount, in whole kobo, adding up to the total exactly.
     *
     * @return array<int, float> month number (from 1) => amount
     */
    public function monthlyAmounts(): array
    {
        return self::split($this->total_amount, (int) $this->months);
    }

    /** @return array<int, float> */
    public static function split(float|int|string $total, int $months): array
    {
        return Money::allocate($total, array_fill(1, max(1, $months), 1));
    }

    /** The day month $sequence is released: the last day of that month. */
    public function dueDate(int $sequence): CarbonInterface
    {
        return Carbon::parse($this->start_date)->startOfMonth()->addMonthsNoOverflow($sequence - 1)->endOfMonth()->startOfDay();
    }

    public function remaining(): float
    {
        return Money::subtract($this->total_amount, $this->released_amount);
    }

    /** Nothing released yet, so it can still be edited or deleted. */
    public function hasReleases(): bool
    {
        return $this->releases()->exists();
    }

    /**
     * The month-by-month plan with each month's state: released (with its
     * journal), due (its month has ended but it isn't posted yet),
     * upcoming, or stopped (cancelled before it was released).
     *
     * @return array<int, array{sequence: int, due_date: CarbonInterface, amount: float, state: string, release: ?AccrualScheduleRelease}>
     */
    public function plan(?CarbonInterface $today = null): array
    {
        $today = ($today ?? today())->copy()->startOfDay();
        $released = $this->releases->keyBy('sequence');
        $rows = [];
        foreach ($this->monthlyAmounts() as $sequence => $amount) {
            $release = $released->get($sequence);
            $due = $this->dueDate($sequence);
            $state = match (true) {
                $release !== null => 'released',
                $this->status === self::STATUS_CANCELLED => 'stopped',
                $due->lte($today) => 'due',
                default => 'upcoming',
            };
            $rows[] = ['sequence' => $sequence, 'due_date' => $due, 'amount' => $release ? (float) $release->amount : $amount, 'state' => $state, 'release' => $release];
        }

        return $rows;
    }

    /** @return array{0: string, 1: string, 2: int} */
    protected static function documentNumberFormat(): array
    {
        return ['schedule_number', 'SCH-', 6];
    }
}
