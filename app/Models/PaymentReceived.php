<?php

namespace App\Models;

use App\Events\PaymentReceivedCreated;
use App\Events\PaymentReceivedDeleted;
use App\Events\PaymentReceivedDeleting;
use App\Events\PaymentReceivedUpdated;
use App\Services\JournalService;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use App\Traits\ValidatesAccountingPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentReceived extends Model
{
    use \App\Traits\HasDocumentNumber;
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes, ValidatesAccountingPeriod;

    protected $table = 'payments_received';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'invoice_id',
        'payment_number',
        'payment_date',
        'amount',
        'payment_method',
        'bank_id',
        'reference',
        'notes',
        'is_deposit',
        'unused_amount',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'unused_amount' => 'decimal:2',
        'is_deposit' => 'boolean',
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

    /** @return BelongsTo<Bank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array{0: string, 1: string, 2: int} */
    protected static function documentNumberFormat(): array
    {
        return ['payment_number', 'PAY-', 6];
    }

    /** @return MorphOne<Journal, $this> */
    public function journal(): MorphOne
    {
        return $this->morphOne(Journal::class, 'reference');
    }

    /**
     * Get all applications of this deposit to invoices
     *
     * @return HasMany<CustomerDepositApplication, $this>
     */
    public function depositApplications(): HasMany
    {
        return $this->hasMany(CustomerDepositApplication::class, 'deposit_payment_id');
    }

    /**
     * Check if this payment is a deposit
     */
    public function isDeposit(): bool
    {
        return (bool) $this->is_deposit;
    }

    /**
     * Check if this deposit has any unused balance
     */
    public function hasUnusedBalance(): bool
    {
        return $this->is_deposit && $this->unused_amount > 0;
    }

    /**
     * Get the amount that has been applied from this deposit
     */
    public function getAppliedAmountAttribute()
    {
        return $this->amount - $this->unused_amount;
    }

    /**
     * Apply a portion of this deposit to an invoice
     */
    public function applyToInvoice(Invoice $invoice, float $amount, ?string $notes = null): ?CustomerDepositApplication
    {
        if (! $this->is_deposit) {
            throw new \InvalidArgumentException('This payment is not a deposit');
        }

        if ($amount > $this->unused_amount) {
            throw new \InvalidArgumentException('Amount exceeds available deposit balance');
        }

        if ($amount > $invoice->balance_due) {
            throw new \InvalidArgumentException('Amount exceeds invoice balance due');
        }

        return \DB::transaction(function () use ($invoice, $amount, $notes) {
            // Create deposit application record
            $application = CustomerDepositApplication::create([
                'tenant_id' => $this->tenant_id,
                'customer_id' => $this->customer_id,
                'deposit_payment_id' => $this->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'application_date' => now(),
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            // Create a payment record for the invoice
            $payment = PaymentReceived::create([
                'tenant_id' => $this->tenant_id,
                'customer_id' => $this->customer_id,
                'invoice_id' => $invoice->id,
                'payment_number' => PaymentReceived::generateNumber($this->tenant_id),
                'payment_date' => now(),
                'amount' => $amount,
                'payment_method' => 'deposit',
                'reference' => "Applied from deposit {$this->payment_number}",
                'notes' => $notes,
                'is_deposit' => false,
                'unused_amount' => 0,
                'created_by' => auth()->id(),
            ]);

            // Update the application with the payment ID
            $application->update(['applied_payment_id' => $payment->id]);

            // Update this deposit's unused amount
            $this->unused_amount -= $amount;
            $this->withoutPeriodValidation()->save();

            // Update customer's deposit balance
            $this->customer->updateDepositBalance();

            return $application;
        });
    }

    /**
     * Create or update journal entry for this payment
     */
    public function createJournalEntry(): ?Journal
    {
        $journalService = app(JournalService::class);

        return $journalService->createPaymentReceivedJournal($this);
    }

    protected static function booted()
    {
        static::created(function ($payment) {
            PaymentReceivedCreated::dispatch($payment);
        });

        static::updated(function ($payment) {
            PaymentReceivedUpdated::dispatch($payment);
        });

        static::deleting(function ($payment) {
            PaymentReceivedDeleting::dispatch($payment);
        });

        static::deleted(function ($payment) {
            PaymentReceivedDeleted::dispatch($payment);
        });
    }
}
