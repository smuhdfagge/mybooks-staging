<?php

namespace App\Models;

use App\Support\Money;
use App\Traits\BelongsToTenant;
use App\Traits\HasDocumentNumber;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A prepaid expense (paid in advance, e.g. a year's rent) or income
 * received in advance (deferred revenue), released to the profit and loss
 * one month at a time by the accruals:release command.
 *
 * Month n is released at the end of the n-th month counted from the start
 * date's month. Each month's amount is the total shared equally, in whole
 * kobo, with the last month taking any rounding difference.
 */
class AccrualSchedule extends Model
{
    use BelongsToTenant, HasDocumentNumber;

    public const TYPE_PREPAID = 'prepaid_expense';

    public const TYPE_DEFERRED = 'deferred_revenue';

    public const TYPES = [
        self::TYPE_PREPAID => 'Prepaid expense (paid in advance)',
        self::TYPE_DEFERRED => 'Income received in advance',
    ];

    public const FUNDING = [
        'bank' => 'Paid or received now, from or into a bank or cash account',
        'reclassify' => 'Already recorded in the expense or income account: move it',
        'existing' => 'Already in the prepaid or deferred account: post nothing now',
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id', 'schedule_number', 'type', 'description', 'total_amount', 'recorded_date', 'start_date', 'months',
        'balance_account_id', 'pl_account_id', 'funding', 'funding_account_id', 'status', 'released_amount',
        'notes', 'created_by', 'cancelled_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'released_amount' => 'decimal:2',
        'recorded_date' => 'date',
        'start_date' => 'date',
        'months' => 'integer',
        'cancelled_at' => 'datetime',
    ];

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

    /** @return BelongsTo<ChartOfAccount, $this> */
    public function fundingAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'funding_account_id');
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

    /** @return array<int, float> month number => amount */
    public function monthlyAmounts(): array
    {
        $weights = array_fill(1, max(1, (int) $this->months), 1);

        return Money::allocate((float) $this->total_amount, $weights);
    }

    /** The date month $sequence is released (the end of that month). */
    public function dueDate(int $sequence): CarbonInterface
    {
        return Carbon::parse($this->start_date)->startOfMonth()->addMonthsNoOverflow($sequence - 1)->endOfMonth()->startOfDay();
    }

    public function remaining(): float
    {
        return Money::subtract($this->total_amount, $this->released_amount);
    }

    /** @return array{0: string, 1: string, 2: int} */
    protected static function documentNumberFormat(): array
    {
        return ['schedule_number', 'SCH-', 6];
    }
}
