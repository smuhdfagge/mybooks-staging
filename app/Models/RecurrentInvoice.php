<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;

class RecurrentInvoice extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'profile_name',
        'frequency',
        'start_date',
        'end_date',
        'next_invoice_date',
        'payment_terms',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'discount_type',
        'total',
        'status',
        'notes',
        'terms',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_invoice_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'payment_terms' => 'integer',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<RecurrentInvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RecurrentInvoiceItem::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'recurrent_invoice_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if this profile is active and due for generation
     */
    public function isDue(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->end_date && $this->next_invoice_date->gt($this->end_date)) {
            return false;
        }

        return $this->next_invoice_date->lte(now());
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Advance the next_invoice_date based on frequency
     */
    public function advanceNextDate(): void
    {
        $this->next_invoice_date = match ($this->frequency) {
            'weekly' => $this->next_invoice_date->addWeek(),
            'monthly' => $this->next_invoice_date->addMonth(),
            'quarterly' => $this->next_invoice_date->addMonths(3),
            'yearly' => $this->next_invoice_date->addYear(),
        };

        // Auto-stop if past end date
        if ($this->end_date && $this->next_invoice_date->gt($this->end_date)) {
            $this->status = 'stopped';
        }

        $this->save();
    }
}
