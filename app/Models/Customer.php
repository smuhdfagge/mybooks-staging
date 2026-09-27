<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;

class Customer extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity, Notifiable;

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
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'credit_limit' => 'decimal:2',
        'deposit_balance' => 'decimal:2',
        'tax_number' => 'encrypted',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function salesReceipts()
    {
        return $this->hasMany(SalesReceipt::class);
    }

    public function payments()
    {
        return $this->hasMany(PaymentReceived::class);
    }

    /**
     * Get all deposits made by this customer
     */
    public function deposits()
    {
        return $this->hasMany(PaymentReceived::class)->where('is_deposit', true);
    }

    /**
     * Get all deposits with unused balance
     */
    public function availableDeposits()
    {
        return $this->hasMany(PaymentReceived::class)
            ->where('is_deposit', true)
            ->where('unused_amount', '>', 0);
    }

    /**
     * Get all deposit applications for this customer
     */
    public function depositApplications()
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

    public function getOutstandingBalanceAttribute()
    {
        return $this->invoices()->where('status', '!=', 'paid')->sum('balance_due');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
