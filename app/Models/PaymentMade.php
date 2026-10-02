<?php

namespace App\Models;

use App\Events\PaymentMadeCreated;
use App\Events\PaymentMadeDeleted;
use App\Events\PaymentMadeDeleting;
use App\Events\PaymentMadeUpdated;
use App\Services\JournalService;
use App\Support\Money;
use App\Traits\BelongsToTenant;
use App\Traits\HasDocumentNumber;
use App\Traits\LogsActivity;
use App\Traits\ValidatesAccountingPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'wht_rate_id',
        'wht_rate',
        'wht_amount',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'wht_rate' => 'decimal:2',
        'wht_amount' => 'decimal:2',
    ];

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

    /** @return BelongsTo<WhtRate, $this> */
    public function whtRate(): BelongsTo
    {
        return $this->belongsTo(WhtRate::class);
    }

    /**
     * Money that actually moved through the bank: the amount less any
     * withholding tax taken off it (tax pack 2). The amount itself is what
     * the bill is settled by.
     */
    public function cashAmount(): float
    {
        return Money::subtract($this->amount, $this->wht_amount ?? 0);
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
