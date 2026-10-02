<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'company_name',
        'tax_number',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'payment_terms',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tax_number' => 'encrypted',
    ];

    /** @return HasMany<Bill, $this> */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @return HasMany<PaymentMade, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(PaymentMade::class);
    }

    /** @return HasMany<VendorCredit, $this> */
    public function vendorCredits(): HasMany
    {
        return $this->hasMany(VendorCredit::class);
    }

    /**
     * Money paid to this supplier before a bill.
     *
     * @return HasMany<PaymentMade, $this>
     */
    public function advances(): HasMany
    {
        return $this->hasMany(PaymentMade::class)->where('is_advance', true);
    }

    /** Supplier credit not yet used or refunded. */
    public function openCreditBalance(): float
    {
        return round((float) $this->vendorCredits()->where('status', 'open')->sum('balance'), 2);
    }

    /** Advances not yet used against bills. */
    public function advanceBalance(): float
    {
        return round((float) $this->advances()->sum('unused_amount'), 2);
    }

    public function getTotalPurchasesAttribute($value)
    {
        // Already loaded by withBalances(): no query per row (P4).
        if (array_key_exists('total_purchases', $this->attributes)) {
            return $value ?? 0;
        }

        return $this->bills()->sum('total');
    }

    public function getOutstandingBalanceAttribute($value)
    {
        if (array_key_exists('outstanding_balance', $this->attributes)) {
            return $value ?? 0;
        }

        return $this->bills()->where('status', '!=', 'paid')->sum('balance_due');
    }

    /**
     * Loads total purchases and the outstanding balance with the list
     * query instead of two queries per vendor (P4).
     */
    public function scopeWithBalances($query)
    {
        return $query
            ->withSum('bills as total_purchases', 'total')
            ->withSum(['bills as outstanding_balance' => fn ($q) => $q->where('status', '!=', 'paid')], 'balance_due');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
