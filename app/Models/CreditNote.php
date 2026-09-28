<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditNote extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    const STATUS_DRAFT = 'draft';

    const STATUS_OPEN = 'open';

    const STATUS_CLOSED = 'closed';

    const STATUS_VOID = 'void';

    const REASONS = [
        'product_return' => 'Product Return',
        'defective_goods' => 'Defective Goods',
        'pricing_error' => 'Pricing Error',
        'order_cancellation' => 'Order Cancellation',
        'duplicate_invoice' => 'Duplicate Invoice',
        'goodwill' => 'Goodwill / Courtesy',
        'other' => 'Other',
    ];

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'invoice_id',
        'credit_note_number',
        'credit_note_date',
        'status',
        'reason',
        'subtotal',
        'tax_amount',
        'total',
        'balance',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'credit_note_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return HasMany<CreditNoteItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    /** @return HasMany<CreditNoteApplication, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(CreditNoteApplication::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateNumber($tenantId): string
    {
        $last = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();

        $number = $last ? intval(substr($last->credit_note_number, 3)) + 1 : 1;

        return 'CN-'.str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Apply this credit note (or part of it) to one of the same customer's
     * invoices. Call inside a transaction.
     */
    public function applyToInvoice(Invoice $invoice, float $amount): CreditNoteApplication
    {
        $amount = round($amount, 2);

        if ($this->status !== self::STATUS_OPEN) {
            throw new \InvalidArgumentException('Only an open credit note can be applied.');
        }
        if ((int) $invoice->customer_id !== (int) $this->customer_id) {
            throw new \InvalidArgumentException('The invoice belongs to a different customer.');
        }
        if (in_array($invoice->status, ['draft', 'cancelled', 'void'], true)) {
            throw new \InvalidArgumentException("Invoice {$invoice->invoice_number} is {$invoice->status}.");
        }
        if ($amount <= 0 || $amount - (float) $this->balance > 0.005) {
            throw new \InvalidArgumentException('Amount exceeds credit note balance.');
        }
        if ($amount - (float) $invoice->balance_due > 0.005) {
            throw new \InvalidArgumentException('Amount exceeds invoice balance due.');
        }

        $application = CreditNoteApplication::create([
            'credit_note_id' => $this->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'applied_date' => now(),
            'applied_by' => auth()->id(),
        ]);

        $this->balance = round((float) $this->balance - $amount, 2);
        if ($this->balance <= 0) {
            $this->status = self::STATUS_CLOSED;
        }
        $this->save();

        // Recompute from payments + credits, rather than adding on (N5)
        $invoice->updateBalances();

        return $application;
    }

    /**
     * Get the total applied amount.
     */
    public function getTotalAppliedAttribute(): float
    {
        return (float) $this->applications()->sum('amount');
    }

    /**
     * Open the credit note: it can now be applied to invoices, and the
     * ledger is credited (finding N5 - it used to post nothing).
     */
    public function open(): bool
    {
        if ($this->status !== self::STATUS_DRAFT) {
            return false;
        }

        \Illuminate\Support\Facades\DB::transaction(function () {
            $this->update([
                'status' => self::STATUS_OPEN,
                'balance' => $this->total,
            ]);
            app(\App\Services\JournalService::class)->createCreditNoteJournal($this);
        });

        return true;
    }

    /**
     * Void an unused credit note, reversing its journal if it was opened.
     */
    public function void(): bool
    {
        if ($this->status === self::STATUS_VOID || $this->total_applied > 0) {
            return false;
        }

        \Illuminate\Support\Facades\DB::transaction(function () {
            if ($this->status !== self::STATUS_DRAFT) {
                app(\App\Services\JournalService::class)
                    ->reverseDocumentJournal(self::class, $this->id, 'Credit note voided');
            }
            $this->update(['status' => self::STATUS_VOID, 'balance' => 0]);
        });

        return true;
    }
}
