<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;
use App\Traits\ValidatesAccountingPeriod;

class CustomerDepositApplication extends Model
{
    use HasFactory, BelongsToTenant, ValidatesAccountingPeriod;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'deposit_payment_id',
        'invoice_id',
        'applied_payment_id',
        'amount',
        'application_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'application_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the customer this application belongs to
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the original deposit payment
     */
    public function depositPayment()
    {
        return $this->belongsTo(PaymentReceived::class, 'deposit_payment_id');
    }

    /**
     * Get the invoice this deposit was applied to
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the payment record created when applying the deposit
     */
    public function appliedPayment()
    {
        return $this->belongsTo(PaymentReceived::class, 'applied_payment_id');
    }

    /**
     * Get the user who created this application
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
