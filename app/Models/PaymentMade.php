<?php

namespace App\Models;

use App\Events\PaymentMadeCreated;
use App\Events\PaymentMadeDeleted;
use App\Events\PaymentMadeDeleting;
use App\Events\PaymentMadeUpdated;
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

class PaymentMade extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes, ValidatesAccountingPeriod;
    use HasDocumentNumber;

    protected $table = 'payments_made';

    protected $fillable = [
        'tenant_id',
        'vendor_id',
        'bill_id',
        'payment_number',
        'payment_date',
        'amount',
        'payment_method',
        'bank_id',
        'reference',
        'notes',
        'is_advance',
        'unused_amount',
        'created_by',
        'wht_category_id',
        'wht_rate',
        'wht_base',
        'wht_amount',
        'wht_authority',
        'wht_state',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'unused_amount' => 'decimal:2',
        'is_advance' => 'boolean',
        'wht_rate' => 'decimal:2',
        'wht_base' => 'decimal:2',
        'wht_amount' => 'decimal:2',
    ];

    /** Payment method of the payment that uses an advance against a bill. */
    public const METHOD_ADVANCE = 'advance';

    /** @return BelongsTo<WhtCategory, $this> */
    public function whtCategory(): BelongsTo
    {
        return $this->belongsTo(WhtCategory::class);
    }

    /** What this payment settles on the bill: money paid plus WHT withheld. */
    public function settledAmount(): float
    {
        return round((float) $this->amount + (float) $this->wht_amount, 2);
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<Bill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
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
        return ['payment_number', 'PM-', 6];
    }

    /**
     * Where this advance has been used against bills.
     *
     * @return HasMany<VendorAdvanceApplication, $this>
     */
    public function advanceApplications(): HasMany
    {
        return $this->hasMany(VendorAdvanceApplication::class, 'advance_payment_id');
    }

    /**
     * Use part of this supplier advance against one of the same supplier's
     * bills, like PaymentReceived::applyToInvoice for customer deposits.
     * A payment with method "advance" clears the bill and posts
     * Dr accounts payable / Cr supplier advances. Call inside a transaction
     * with this row locked.
     */
    public function applyToBill(Bill $bill, float $amount, ?string $date = null, ?string $notes = null): VendorAdvanceApplication
    {
        $amount = round($amount, 2);
        if (! $this->is_advance) {
            throw new \InvalidArgumentException('This payment is not a supplier advance.');
        }
        if ((int) $bill->vendor_id !== (int) $this->vendor_id) {
            throw new \InvalidArgumentException('The bill belongs to a different supplier.');
        }
        if (in_array($bill->status, ['draft', 'cancelled'], true)) {
            throw new \InvalidArgumentException("Bill {$bill->bill_number} is {$bill->status}.");
        }
        if ($amount <= 0 || $amount - (float) $this->unused_amount > 0.005) {
            throw new \InvalidArgumentException('Amount is more than is left of the advance.');
        }
        if ($amount - (float) $bill->balance_due > 0.005) {
            throw new \InvalidArgumentException('Amount is more than the bill still owes.');
        }

        $date ??= now()->toDateString();

        $application = VendorAdvanceApplication::create([
            'tenant_id' => $this->tenant_id,
            'vendor_id' => $this->vendor_id,
            'advance_payment_id' => $this->id,
            'bill_id' => $bill->id,
            'amount' => $amount,
            'application_date' => $date,
            'notes' => $notes,
            'created_by' => auth()->id(),
        ]);

        $payment = PaymentMade::create([
            'tenant_id' => $this->tenant_id,
            'vendor_id' => $this->vendor_id,
            'bill_id' => $bill->id,
            'payment_number' => PaymentMade::generateNumber($this->tenant_id),
            'payment_date' => $date,
            'amount' => $amount,
            'payment_method' => self::METHOD_ADVANCE,
            'reference' => "From advance {$this->payment_number}",
            'notes' => $notes,
            'is_advance' => false,
            'unused_amount' => 0,
            'created_by' => auth()->id(),
        ]);

        $application->update(['applied_payment_id' => $payment->id]);

        $this->unused_amount = round((float) $this->unused_amount - $amount, 2);
        $this->withoutPeriodValidation()->save();

        return $application;
    }

    /** @return MorphOne<Journal, $this> */
    public function journal(): MorphOne
    {
        return $this->morphOne(Journal::class, 'reference');
    }

    /**
     * Create or update journal entry for this payment
     */
    public function createJournalEntry(): ?Journal
    {
        $journalService = app(JournalService::class);

        return $journalService->createPaymentMadeJournal($this);
    }

    protected static function booted()
    {
        static::created(function ($payment) {
            PaymentMadeCreated::dispatch($payment);
        });

        static::updated(function ($payment) {
            PaymentMadeUpdated::dispatch($payment);
        });

        static::deleting(function ($payment) {
            PaymentMadeDeleting::dispatch($payment);
        });

        static::deleted(function ($payment) {
            PaymentMadeDeleted::dispatch($payment);
        });
    }
}
