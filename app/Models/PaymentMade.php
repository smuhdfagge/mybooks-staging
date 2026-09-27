<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use App\Traits\ValidatesAccountingPeriod;
use App\Events\PaymentMadeCreated;
use App\Events\PaymentMadeUpdated;
use App\Events\PaymentMadeDeleting;
use App\Events\PaymentMadeDeleted;
use App\Services\JournalService;

class PaymentMade extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity, ValidatesAccountingPeriod;

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
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateNumber($tenantId)
    {
        $lastPayment = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();
        
        $number = $lastPayment ? intval(substr($lastPayment->payment_number, 3)) + 1 : 1;
        return 'PM-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function journal()
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
