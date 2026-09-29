<?php

namespace App\Models;

use App\Events\InvoiceRefundDeleting;
use App\Services\JournalService;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceRefund extends Model
{
    use \App\Traits\HasDocumentNumber;
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'customer_id',
        'refund_number',
        'refund_date',
        'amount',
        'refund_method',
        'reason',
        'notes',
        'reference',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'refund_date' => 'date',
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    /**
     * Available refund reasons
     */
    public const REASONS = [
        'customer_request' => 'Customer Request',
        'product_defect' => 'Product Defect',
        'wrong_item' => 'Wrong Item Delivered',
        'duplicate_charge' => 'Duplicate Charge',
        'order_cancelled' => 'Order Cancelled',
        'price_adjustment' => 'Price Adjustment',
        'service_issue' => 'Service Issue',
        'other' => 'Other',
    ];

    /**
     * Available refund methods
     */
    public const METHODS = [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank Transfer',
        'check' => 'Check',
        'credit_card' => 'Credit Card',
        'store_credit' => 'Store Credit',
        'original_method' => 'Original Payment Method',
        'other' => 'Other',
    ];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    /** @return MorphOne<Journal, $this> */
    public function journal(): MorphOne
    {
        return $this->morphOne(Journal::class, 'reference');
    }

    /** @return array{0: string, 1: string, 2: int} */
    protected static function documentNumberFormat(): array
    {
        return ['refund_number', 'REF-', 6];
    }

    /**
     * Check if this is a full refund
     */
    public function isFullRefund(): bool
    {
        return $this->amount >= $this->invoice->amount_paid;
    }

    /**
     * Create journal entry for this refund
     */
    public function createJournalEntry(): ?Journal
    {
        $journalService = app(JournalService::class);

        return $journalService->createRefundJournal($this);
    }

    /**
     * Process the refund and update related records
     */
    public function process(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        // Update refund status
        $this->status = 'completed';
        $this->approved_by = auth()->id();
        $this->approved_at = now();
        $this->save();

        // A refund gives back money and reverses that part of the sale (the
        // journal debits revenue and VAT, credits cash). Payments received
        // stay as they were, so what the customer owes doesn't change: a
        // paid invoice refunded in full is settled, not owed again (A6).
        $invoice = $this->invoice;
        $invoice->total_refunded = round((float) ($invoice->total_refunded ?? 0) + (float) $this->amount, 2);
        $invoice->updateBalances();

        // Create journal entry for the refund
        $this->createJournalEntry();

        return true;
    }

    /**
     * Cancel the refund
     */
    public function cancel(): bool
    {
        if ($this->status === 'cancelled') {
            return false;
        }

        // If already completed, reverse the effects
        if ($this->status === 'completed') {
            $invoice = $this->invoice;
            $invoice->total_refunded = max(0, round((float) ($invoice->total_refunded ?? 0) - (float) $this->amount, 2));
            $invoice->updateBalances();

            // Delete the journal entry
            $journalService = app(JournalService::class);
            $journalService->deleteJournalForTransaction(InvoiceRefund::class, $this->id, $this->tenant_id);
        }

        $this->status = 'cancelled';
        $this->save();

        return true;
    }

    protected static function booted()
    {
        static::deleting(function ($refund) {
            InvoiceRefundDeleting::dispatch($refund);
        });
    }
}
