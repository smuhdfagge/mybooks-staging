<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\ValidatesAccountingPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDepositApplication extends Model
{
    use BelongsToTenant, HasFactory, ValidatesAccountingPeriod;

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
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the original deposit payment
     *
     * @return BelongsTo<PaymentReceived, $this>
     */
    public function depositPayment(): BelongsTo
    {
        return $this->belongsTo(PaymentReceived::class, 'deposit_payment_id');
    }

    /**
     * Get the invoice this deposit was applied to
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the payment record created when applying the deposit
     *
     * @return BelongsTo<PaymentReceived, $this>
     */
    public function appliedPayment(): BelongsTo
    {
        return $this->belongsTo(PaymentReceived::class, 'applied_payment_id');
    }

    /**
     * Get the user who created this application
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The date the period and lock date checks use (session 11: it fell back to created_at). */
    protected function getPeriodDateField(): string
    {
        return 'application_date';
    }
}
