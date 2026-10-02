<?php

namespace App\Models;

use App\Events\PaymentReceivedCreated;
use App\Events\PaymentReceivedDeleted;
use App\Events\PaymentReceivedDeleting;
use App\Events\PaymentReceivedUpdated;
use App\Services\JournalService;
use App\Traits\BelongsToTenant;
use App\Traits\HasDocumentNumber;
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
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes, ValidatesAccountingPeriod;
    use HasDocumentNumber;

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
        'wht_category_id',
        'wht_rate',
        'wht_base',
        'wht_amount',
        'wht_authority',
        'wht_state',
        'wht_credit_note_number',
        'wht_credit_note_date',
        'wht_utilisation_id',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'unused_amount' => 'decimal:2',
        'is_deposit' => 'boolean',
        'wht_rate' => 'decimal:2',
        'wht_base' => 'decimal:2',
        'wht_amount' => 'decimal:2',
        'wht_credit_note_date' => 'date',
    ];

    /** WHT credit note states: deducted, credit note in hand, used against income tax. */
    public const WHT_OUTSTANDING = 'outstanding';

    public const WHT_RECEIVED = 'received';

    public const WHT_UTILISED = 'utilised';

    /** @return BelongsTo<WhtCategory, $this> */
    public function whtCategory(): BelongsTo
    {
        return $this->belongsTo(WhtCategory::class);
    }

    /** @return BelongsTo<WhtCreditUtilisation, $this> */
    public function whtUtilisation(): BelongsTo
    {
        return $this->belongsTo(WhtCreditUtilisation::class, 'wht_utilisation_id');
    }

    /** What this payment settles on the invoice: money received plus WHT the customer deducted. */
    public function settledAmount(): float
    {
        return round((float) $this->amount + (float) $this->wht_amount, 2);
    }

    /** Plain words for each WHT credit note state. */
    public const WHT_STATUS_LABELS = [
        self::WHT_OUTSTANDING => 'Awaiting credit note',
        self::WHT_RECEIVED => 'Credit note received',
        self::WHT_UTILISED => 'Used against income tax',
    ];

    public function whtStatus(): ?string
    {
        if ((float) $this->wht_amount <= 0) {
            return null;
        }
        if ($this->wht_utilisation_id) {
            return self::WHT_UTILISED;
        }

        return $this->wht_credit_note_number ? self::WHT_RECEIVED : self::WHT_OUTSTANDING;
    }

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
