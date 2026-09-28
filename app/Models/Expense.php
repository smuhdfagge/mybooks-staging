<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use App\Traits\ValidatesAccountingPeriod;
use App\Events\ExpensePaid;
use App\Events\ExpenseDeleting;
use App\Services\JournalService;

class Expense extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity, ValidatesAccountingPeriod, \App\Traits\KeepsTotalsBalanced;

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PAID = 'paid';

    protected $fillable = [
        'tenant_id',
        'vendor_id',
        'expense_account_id',
        'paid_through_id',
        'bank_id',
        'expense_number',
        'name',
        'expense_date',
        'amount',
        'tax_amount',
        'total',
        'payment_method',
        'reference',
        'description',
        'is_billable',
        'customer_id',
        'notes',
        'created_by',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'rejected_by',
        'rejected_at',
        'submitted_at',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'is_billable' => 'boolean',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function expenseAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    public function paidThroughAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'paid_through_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedByUser()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public static function generateNumber($tenantId)
    {
        $lastExpense = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();
        
        $number = $lastExpense ? intval(substr($lastExpense->expense_number, 4)) + 1 : 1;
        return 'EXP-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function journal()
    {
        return $this->morphOne(Journal::class, 'reference');
    }

    /**
     * Create or update journal entry for this expense
     */
    public function createJournalEntry(): ?Journal
    {
        $journalService = app(JournalService::class);
        return $journalService->createExpenseJournal($this);
    }

    /**
     * Get all available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING_APPROVAL => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_PAID => 'Paid',
        ];
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Get status badge color class
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_PENDING_APPROVAL => 'yellow',
            self::STATUS_APPROVED => 'blue',
            self::STATUS_REJECTED => 'red',
            self::STATUS_PAID => 'green',
            default => 'gray',
        };
    }

    /**
     * Check if expense is in draft status
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if expense is pending approval
     */
    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    /**
     * Check if expense is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if expense is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if expense is paid
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Check if expense can be edited
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED]);
    }

    /**
     * Check if expense can be submitted for approval
     */
    public function canBeSubmitted(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED]);
    }

    /**
     * Check if expense can be approved
     */
    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    /**
     * Check if expense can be rejected
     */
    public function canBeRejected(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    /**
     * Check if expense can be marked as paid
     */
    public function canBeMarkedAsPaid(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Submit expense for approval
     */
    public function submitForApproval(): bool
    {
        if (!$this->canBeSubmitted()) {
            return false;
        }

        $this->withoutPeriodValidation()->update([
            'status' => self::STATUS_PENDING_APPROVAL,
            'submitted_at' => now(),
            'rejection_reason' => null,
            'rejected_by' => null,
            'rejected_at' => null,
        ]);

        return true;
    }

    /**
     * Approve the expense
     */
    public function approve(int $approverId): bool
    {
        if (!$this->canBeApproved()) {
            return false;
        }

        $this->withoutPeriodValidation()->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        return true;
    }

    /**
     * Reject the expense
     */
    public function reject(int $rejectorId, ?string $reason = null): bool
    {
        if (!$this->canBeRejected()) {
            return false;
        }

        $this->withoutPeriodValidation()->update([
            'status' => self::STATUS_REJECTED,
            'rejected_by' => $rejectorId,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return true;
    }

    /**
     * Mark expense as paid and process payment
     */
    public function markAsPaid(): bool
    {
        if (!$this->canBeMarkedAsPaid()) {
            return false;
        }

        $this->withoutPeriodValidation()->update([
            'status' => self::STATUS_PAID,
        ]);

        ExpensePaid::dispatch($this);

        return true;
    }

    /**
     * Scope to filter by status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get pending approval expenses
     */
    public function scopePendingApproval($query)
    {
        return $query->where('status', self::STATUS_PENDING_APPROVAL);
    }

    /**
     * Scope to get approved expenses
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    protected static function booted()
    {
        static::deleting(function ($expense) {
            ExpenseDeleting::dispatch($expense);
        });
    }

    /**
     * total = amount + tax_amount (see KeepsTotalsBalanced).
     */
    protected function documentTotalParts(): array
    {
        return [['amount', 'tax_amount'], []];
    }
}
