<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use App\Traits\ValidatesAccountingPeriod;
use App\Events\PayrollPaid;
use App\Events\PayrollDeleting;
use App\Services\JournalService;

class Payroll extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity, ValidatesAccountingPeriod;

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'payroll_batch_id',
        'employee_id',
        'salary_structure_id',
        'salary_structure_snapshot',
        'payroll_number',
        'pay_period_start',
        'pay_period_end',
        'pay_date',
        'basic_salary',
        'allowances',
        'allowance_details',
        'overtime_hours',
        'overtime_amount',
        'gross_salary',
        'tax_deduction',
        'other_deductions',
        'deduction_details',
        'employer_contributions',
        'employer_contribution_details',
        'total_deductions',
        'net_salary',
        'status',
        'payment_method',
        'payment_reference',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'pay_period_start' => 'date',
        'pay_period_end' => 'date',
        'pay_date' => 'date',
        'approved_at' => 'datetime',
        'basic_salary' => 'decimal:2',
        'allowances' => 'decimal:2',
        'allowance_details' => 'array',
        'overtime_hours' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'tax_deduction' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'deduction_details' => 'array',
        'employer_contributions' => 'decimal:2',
        'employer_contribution_details' => 'array',
        'salary_structure_snapshot' => 'array',
        'total_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<PayrollBatch, $this> */
    public function payrollBatch(): BelongsTo
    {
        return $this->belongsTo(PayrollBatch::class);
    }

    /** @return BelongsTo<SalaryStructure, $this> */
    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
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

    public static function generateNumber($tenantId)
    {
        $lastPayroll = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();
        
        $number = $lastPayroll ? intval(substr($lastPayroll->payroll_number, 4)) + 1 : 1;
        return 'PAY-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function calculateTotals()
    {
        $this->gross_salary = $this->basic_salary + $this->allowances + $this->overtime_amount;
        $this->total_deductions = $this->tax_deduction + $this->other_deductions;
        $this->net_salary = $this->gross_salary - $this->total_deductions;
    }

    /** @return MorphOne<Journal, $this> */
    public function journal(): MorphOne
    {
        return $this->morphOne(Journal::class, 'reference');
    }

    /**
     * Create or update journal entry for this payroll
     */
    public function createJournalEntry(): ?Journal
    {
        $journalService = app(JournalService::class);
        return $journalService->createPayrollJournal($this);
    }

    /**
     * Mark payroll as paid and create journal entry
     */
    public function markAsPaid(): bool
    {
        if ($this->status !== self::STATUS_APPROVED) {
            return false;
        }

        $this->withoutPeriodValidation()->update(['status' => self::STATUS_PAID]);

        PayrollPaid::dispatch($this);

        return true;
    }

    /**
     * Check if payroll is paid
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    protected static function booted()
    {
        static::deleting(function ($payroll) {
            PayrollDeleting::dispatch($payroll);
        });
    }
}
