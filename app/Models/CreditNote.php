<?php

namespace App\Models;

use App\Actions\CreditNotes\OpenCreditNote;
use App\Actions\CreditNotes\VoidCreditNote;
use App\Enums\CreditNoteStatus;
use App\Traits\BelongsToTenant;
use App\Traits\GuardsStatusTransitions;
use App\Traits\HasDocumentNumber;
use App\Traits\HasWarehouse;
use App\Traits\KeepsTotalsBalanced;
use App\Traits\LogsActivity;
use App\Traits\ValidatesAccountingPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

/**
 * A customer credit note: goods returned, or a price reduction, optionally
 * against one invoice. Opening it posts the journal and puts returned goods
 * back into stock (App\Actions\CreditNotes). The open balance can be
 * applied to the customer's unpaid invoices or refunded to them.
 */
class CreditNote extends Model
{
    use BelongsToTenant, HasFactory, HasWarehouse, KeepsTotalsBalanced, LogsActivity, SoftDeletes, ValidatesAccountingPeriod;
    use GuardsStatusTransitions, HasDocumentNumber;

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
        'restock',
        'subtotal',
        'tax_amount',
        'total',
        'balance',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'credit_note_date' => 'date',
        'restock' => 'boolean',
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

    /** @return HasMany<CreditNoteRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(CreditNoteRefund::class);
    }

    /** @return MorphMany<Journal, $this> */
    public function journals(): MorphMany
    {
        return $this->morphMany(Journal::class, 'reference');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array{0: string, 1: string, 2: int} */
    protected static function documentNumberFormat(): array
    {
        return ['credit_note_number', 'CN-', 6];
    }

    public function isDraft(): bool
    {
        return $this->status === CreditNoteStatus::Draft->value;
    }

    public function isOpen(): bool
    {
        return $this->status === CreditNoteStatus::Open->value;
    }

    /**
     * Get the total applied amount.
     */
    public function getTotalAppliedAttribute(): float
    {
        return (float) $this->applications()->sum('amount');
    }

    /** Applied to invoices plus refunded. */
    public function usedAmount(): float
    {
        return round((float) $this->applications()->sum('amount') + (float) $this->refunds()->sum('amount'), 2);
    }

    /** Lower the open balance; the credit note closes when nothing is left. */
    public function reduceBalance(float $amount): void
    {
        $this->balance = round((float) $this->balance - $amount, 2);
        if ($this->balance <= 0.004) {
            $this->balance = 0;
            $this->status = CreditNoteStatus::Closed->value;
        }
        $this->withoutPeriodValidation()->save();
    }

    /**
     * Open (post) the credit note: see OpenCreditNote. Returns false if it
     * isn't a draft.
     */
    public function open(): bool
    {
        if ($this->status !== CreditNoteStatus::Draft->value) {
            return false;
        }

        app(OpenCreditNote::class)->handle($this);
        $this->refresh();

        return true;
    }

    /**
     * Void an unused credit note: see VoidCreditNote. Returns false if it
     * can't be voided.
     */
    public function void(): bool
    {
        try {
            app(VoidCreditNote::class)->handle($this);
        } catch (ValidationException) {
            return false;
        }
        $this->refresh();

        return true;
    }

    protected function getPeriodDateField(): string
    {
        return 'credit_note_date';
    }

    /**
     * total = subtotal + tax_amount (see KeepsTotalsBalanced, Q2).
     */
    protected function documentTotalParts(): array
    {
        return [['subtotal', 'tax_amount'], []];
    }

    /** Allowed status moves. */
    protected static function statusEnum(): string
    {
        return CreditNoteStatus::class;
    }

    protected static function statusDocumentName(): string
    {
        return 'credit note';
    }
}
