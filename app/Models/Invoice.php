<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use App\Traits\ValidatesAccountingPeriod;
use App\Events\InvoiceSaved;
use App\Events\InvoiceDeleting;
use App\Services\JournalService;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity, ValidatesAccountingPeriod;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'sales_order_id',
        'invoice_number',
        'reference',
        'invoice_date',
        'due_date',
        'status',
        'released_at',
        'waybill_number',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'discount_type',
        'total',
        'amount_paid',
        'balance_due',
        'total_refunded',
        'notes',
        'terms',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'released_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'total_refunded' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(PaymentReceived::class);
    }

    public function refunds()
    {
        return $this->hasMany(InvoiceRefund::class);
    }

    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class);
    }

    public function creditNoteApplications()
    {
        return $this->hasMany(CreditNoteApplication::class);
    }

    public function deliveryNotes()
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journal()
    {
        return $this->morphOne(Journal::class, 'reference');
    }

    /**
     * Create or update journal entry for this invoice
     */
    public function createJournalEntry(): ?Journal
    {
        $journalService = app(JournalService::class);
        return $journalService->createInvoiceJournal($this);
    }

    protected static function booted()
    {
        static::saved(function ($invoice) {
            if ($invoice->total > 0 && $invoice->status !== 'draft') {
                InvoiceSaved::dispatch($invoice);
            }
        });

        static::deleting(function ($invoice) {
            InvoiceDeleting::dispatch($invoice);
        });
    }

    public static function generateNumber($tenantId)
    {
        $lastInvoice = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();
        
        $number = $lastInvoice ? intval(substr($lastInvoice->invoice_number, 4)) + 1 : 1;
        return 'INV-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function updateBalances()
    {
        $this->amount_paid = $this->payments()->sum('amount');
        $this->balance_due = $this->total - $this->amount_paid;
        $this->status = $this->balance_due <= 0 ? 'paid' : ($this->amount_paid > 0 ? 'partial' : 'unpaid');
        $this->withoutPeriodValidation()->save();
    }

    public static function generateWaybillNumber($tenantId)
    {
        $lastWaybill = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('waybill_number')
            ->latest('id')
            ->first();
        
        $number = $lastWaybill ? intval(substr($lastWaybill->waybill_number, 3)) + 1 : 1;
        return 'WB-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function isReleased()
    {
        return $this->released_at !== null;
    }

    public function canBeReleased()
    {
        return $this->status === 'paid' && !$this->isReleased();
    }

    /**
     * Release inventory reservation for invoice items
     * This moves quantity from reserved back to available
     * Only releases for items that track inventory (products, not services)
     */
    public function releaseInventoryReservation(): void
    {
        // Skip if invoice has already been released (inventory already deducted)
        if ($this->isReleased()) {
            return;
        }

        $tenantId = $this->tenant_id;

        foreach ($this->items as $invoiceItem) {
            if ($invoiceItem->item_id) {
                // Get the item to check if it tracks inventory
                $item = Item::find($invoiceItem->item_id);

                // Skip for services or items that don't track inventory
                if (!$item || !$item->track_inventory || $item->type === 'service') {
                    continue;
                }

                $inventory = Inventory::where('item_id', $invoiceItem->item_id)
                    ->where('tenant_id', $tenantId)
                    ->first();

                if ($inventory) {
                    $inventory->reserved_quantity = max(0, ($inventory->reserved_quantity ?? 0) - $invoiceItem->quantity);
                    $inventory->save();

                    // Record inventory history
                    InventoryHistory::create([
                        'tenant_id' => $tenantId,
                        'item_id' => $invoiceItem->item_id,
                        'type' => 'unreserved',
                        'quantity' => -$invoiceItem->quantity,
                        'reference_type' => 'invoice',
                        'reference_id' => $this->id,
                        'notes' => "Released reservation for Invoice #{$this->invoice_number}",
                        'created_by' => auth()->id(),
                    ]);
                }
            }
        }
    }
}
