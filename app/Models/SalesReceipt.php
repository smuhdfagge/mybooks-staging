<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use App\Traits\ValidatesAccountingPeriod;
use App\Events\SalesReceiptSaved;
use App\Events\SalesReceiptDeleting;
use App\Services\JournalService;

class SalesReceipt extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity, ValidatesAccountingPeriod;

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

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SalesReceiptItem::class);
    }

    public function createdBy()
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
        return 'SR-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function journal()
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
}
