<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class Customer extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'company_name',
        'tax_number',
        'billing_address',
        'shipping_address',
        'city',
        'state',
        'country',
        'postal_code',
        'credit_limit',
        'deposit_balance',
        'payment_terms',
        'notes',
        'is_active',
        'payee_type',
        'wht_category_id',
        'wht_exempt',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'credit_limit' => 'decimal:2',
        'deposit_balance' => 'decimal:2',
        'tax_number' => 'encrypted',
        'wht_exempt' => 'boolean',
    ];

    /** @return BelongsTo<WhtCategory, $this> */
    public function whtCategory(): BelongsTo
    {
        return $this->belongsTo(WhtCategory::class);
    }

    /** Has a Tax Identification Number on file (WHT is doubled without one). */
    public function hasTin(): bool
    {
        return trim((string) $this->tax_number) !== '';
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<SalesOrder, $this> */
    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    /** @return HasMany<SalesReceipt, $this> */
    public function salesReceipts(): HasMany
    {
        return $this->hasMany(SalesReceipt::class);
    }

    /** @return HasMany<PaymentReceived, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(PaymentReceived::class);
    }

    /**
     * Get all deposits made by this customer
     *
     * @return HasMany<PaymentReceived, $this>
     */
    public function deposits(): HasMany
    {
        return $this->hasMany(PaymentReceived::class)->where('is_deposit', true);
    }

    /**
     * Get all deposits with unused balance
     *
     * @return HasMany<PaymentReceived, $this>
     */
    public function availableDeposits(): HasMany
    {
        return $this->hasMany(PaymentReceived::class)
            ->where('is_deposit', true)
            ->where('unused_amount', '>', 0);
    }

    /**
     * Get all deposit applications for this customer
     *
     * @return HasMany<CustomerDepositApplication, $this>
     */
    public function depositApplications(): HasMany
    {
        return $this->hasMany(CustomerDepositApplication::class);
    }

    /**
     * Recalculate and update the deposit balance
     */
    public function updateDepositBalance(): void
    {
        $this->deposit_balance = $this->availableDeposits()->sum('unused_amount');
        $this->save();
    }

    /**
     * Get total deposits made by this customer
     */
    public function getTotalDepositsAttribute()
    {
        return $this->deposits()->sum('amount');
    }

    /**
     * Get total deposits applied to invoices
     */
    public function getTotalDepositsAppliedAttribute()
    {
        return $this->depositApplications()->sum('amount');
    }

    public function getTotalSalesAttribute()
    {
        return $this->invoices()->sum('total');
    }

    public function getOutstandingBalanceAttribute($value)
    {
        // Already loaded by withBalances(): no query per row (P4).
        if (array_key_exists('outstanding_balance', $this->attributes)) {
            return $value ?? 0;
        }

        return $this->invoices()->where('status', '!=', 'paid')->sum('balance_due');
    }

    /**
     * Loads the outstanding balance with the list query instead of one
     * query per customer (P4).
     */
    public function scopeWithBalances($query)
    {
        return $query->withSum(['invoices as outstanding_balance' => fn ($q) => $q->where('status', '!=', 'paid')], 'balance_due');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
