<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeLoan extends Model
{
    use \App\Traits\HasDocumentNumber;
    use BelongsToTenant, LogsActivity, SoftDeletes;

    const TYPE_LOAN = 'loan';

    const TYPE_ADVANCE = 'advance';

    const TYPE_SALARY_ADVANCE = 'salary_advance';

    const STATUS_ACTIVE = 'active';

    const STATUS_COMPLETED = 'completed';

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_PAUSED = 'paused';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'loan_number',
        'type',
        'description',
        'principal_amount',
        'interest_rate',
        'total_installments',
        'installment_amount',
        'installments_paid',
        'amount_repaid',
        'outstanding_balance',
        'disbursement_date',
        'first_deduction_date',
        'last_deduction_date',
        'status',
        'approved_by',
        'approved_at',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'amount_repaid' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'disbursement_date' => 'date',
        'first_deduction_date' => 'date',
        'last_deduction_date' => 'date',
        'approved_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return HasMany<EmployeeLoanRepayment, $this> */
    public function repayments(): HasMany
    {
        return $this->hasMany(EmployeeLoanRepayment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array{0: string, 1: string, 2: int} */
    protected static function documentNumberFormat(): array
    {
        return ['loan_number', 'LOAN-', 6];
    }

    /**
     * Get active loans for an employee that should be deducted in a given pay period.
     */
    public static function getActiveDeductionsForEmployee(int $employeeId, string $payDate): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('employee_id', $employeeId)
            ->where('status', self::STATUS_ACTIVE)
            ->where('first_deduction_date', '<=', $payDate)
            ->where('outstanding_balance', '>', 0)
            ->get();
    }

    /**
     * Record a repayment against this loan from a payroll run.
     */
    public function recordRepayment(float $amount, ?int $payrollId = null, ?string $deductionDate = null): EmployeeLoanRepayment
    {
        // Cap at outstanding balance
        $amount = min($amount, (float) $this->outstanding_balance);

        $interestPortion = $this->interestPortionFor($amount);

        $principalPortion = round($amount - $interestPortion, 2);

        $repayment = $this->repayments()->create([
            'payroll_id' => $payrollId,
            'installment_number' => $this->installments_paid + 1,
            'amount' => $amount,
            'principal_portion' => $principalPortion,
            'interest_portion' => $interestPortion,
            'remaining_balance' => round((float) $this->outstanding_balance - $principalPortion, 2),
            'deduction_date' => $deductionDate ?? now()->toDateString(),
        ]);

        $this->update([
            'installments_paid' => $this->installments_paid + 1,
            'amount_repaid' => round((float) $this->amount_repaid + $amount, 2),
            'outstanding_balance' => round((float) $this->outstanding_balance - $principalPortion, 2),
            'status' => round((float) $this->outstanding_balance - $principalPortion, 2) <= 0
                ? self::STATUS_COMPLETED
                : self::STATUS_ACTIVE,
        ]);

        return $repayment;
    }

    /**
     * Interest in a repayment of $amount: one month's interest on what is
     * still owed. Used when recording the repayment and when the payroll
     * journal splits the deduction between the advance and interest income.
     */
    public function interestPortionFor(float $amount): float
    {
        if ((float) $this->interest_rate <= 0) {
            return 0.0;
        }

        $interest = round((float) $this->outstanding_balance * (float) $this->interest_rate / 100 / 12, 2);

        return min($interest, $amount);
    }

    /**
     * Paying the loan out puts money owed to the business on the books
     * (Dr Employee Advances, Cr Bank). Finding A9.
     */
    protected static function booted(): void
    {
        static::created(function (self $loan) {
            if ($loan->status === self::STATUS_ACTIVE && (float) $loan->principal_amount > 0) {
                app(\App\Services\JournalService::class)->createLoanDisbursementJournal($loan);
            }
        });
    }

    /**
     * Calculate last deduction date based on first deduction date and installments.
     */
    public function calculateLastDeductionDate(): string
    {
        return $this->first_deduction_date
            ->addMonths($this->total_installments - 1)
            ->endOfMonth()
            ->toDateString();
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isFullyRepaid(): bool
    {
        return (float) $this->outstanding_balance <= 0;
    }
}
