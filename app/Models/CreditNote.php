<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;

class CreditNote extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity;

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

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items()
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    public function applications()
    {
        return $this->hasMany(CreditNoteApplication::class);
    }

    public function createdBy()
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
        return 'CN-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Apply this credit note (or partial amount) to an invoice.
     */
    public function applyToInvoice(Invoice $invoice, float $amount): CreditNoteApplication
    {
        if ($amount > $this->balance) {
            throw new \InvalidArgumentException('Amount exceeds credit note balance.');
        }

        if ($amount > $invoice->balance_due) {
            throw new \InvalidArgumentException('Amount exceeds invoice balance due.');
        }

        $application = CreditNoteApplication::create([
            'credit_note_id' => $this->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'applied_date' => now(),
            'applied_by' => auth()->id(),
        ]);

        // Reduce credit note balance
        $this->balance = $this->balance - $amount;
        if ($this->balance <= 0) {
            $this->status = self::STATUS_CLOSED;
        }
        $this->save();

        // Update invoice
        $invoice->amount_paid = $invoice->amount_paid + $amount;
        $invoice->balance_due = $invoice->total - $invoice->amount_paid;
        if ($invoice->balance_due <= 0) {
            $invoice->status = 'paid';
        } elseif ($invoice->amount_paid > 0) {
            $invoice->status = 'partial';
        }
        $invoice->save();

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
     * Open the credit note (make it available for application).
     */
    public function open(): bool
    {
        if ($this->status !== self::STATUS_DRAFT) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_OPEN,
            'balance' => $this->total,
        ]);

        return true;
    }
}
