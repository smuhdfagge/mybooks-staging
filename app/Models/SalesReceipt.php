<?php

namespace App\Models;

use App\Events\SalesReceiptDeleting;
use App\Events\SalesReceiptSaved;
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

class SalesReceipt extends Model
{
    use \App\Traits\KeepsTotalsBalanced, BelongsToTenant, HasFactory, LogsActivity, SoftDeletes, ValidatesAccountingPeriod;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'receipt_number',
        'receipt_date',
        'payment_method',
        'reference',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<SalesReceiptItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SalesReceiptItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateNumber($tenantId)
    {
        $lastReceipt = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();

        $number = $lastReceipt ? intval(substr($lastReceipt->receipt_number, 3)) + 1 : 1;

        return 'SR-'.str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    /** @return MorphOne<Journal, $this> */
    public function journal(): MorphOne
    {
        return $this->morphOne(Journal::class, 'reference');
    }

    /**
     * Create or update journal entry for this sales receipt
     */
    public function createJournalEntry(): ?Journal
    {
        $journalService = app(JournalService::class);

        return $journalService->createSalesReceiptJournal($this);
    }

    protected static function booted()
    {
        static::saved(function ($receipt) {
            if ($receipt->total > 0) {
                SalesReceiptSaved::dispatch($receipt);
            }
        });

        static::deleting(function ($receipt) {
            SalesReceiptDeleting::dispatch($receipt);
        });
    }

    /**
     * total = subtotal + tax_amount - discount_amount (see KeepsTotalsBalanced).
     */
    protected function documentTotalParts(): array
    {
        return [['subtotal', 'tax_amount'], ['discount_amount']];
    }
}
