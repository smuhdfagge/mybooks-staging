<?php

namespace App\Models;

use App\Events\InvoiceDeleting;
use App\Events\InvoiceSaved;
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

class Invoice extends Model
{
    use \App\Traits\GuardsStatusTransitions, \App\Traits\HasDocumentNumber;
    use \App\Traits\KeepsTotalsBalanced, BelongsToTenant, HasFactory, LogsActivity, SoftDeletes, ValidatesAccountingPeriod;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'sales_order_id',
        'recurrent_invoice_id',
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

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /** @return HasMany<PaymentReceived, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(PaymentReceived::class);
    }

    /** @return HasMany<InvoiceRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(InvoiceRefund::class);
    }

    /** @return HasMany<CreditNote, $this> */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    /** @return HasMany<CreditNoteApplication, $this> */
    public function creditNoteApplications(): HasMany
    {
        return $this->hasMany(CreditNoteApplication::class);
    }

    /** @return HasMany<DeliveryNote, $this> */
    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return MorphOne<Journal, $this> */
    public function journal(): MorphOne
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

    /** @return array{0: string, 1: string, 2: int} */
    protected static function documentNumberFormat(): array
    {
        return ['invoice_number', 'INV-', 6];
    }

    /**
     * amount_paid counts payments and applied credit notes, so recording a
     * payment after a credit doesn't wipe the credit out (N5).
     */
    public function updateBalances()
    {
        $this->amount_paid = round((float) $this->payments()->sum('amount')
            + (float) $this->creditNoteApplications()->sum('amount'), 2);
        $this->balance_due = $this->total - $this->amount_paid;
        $this->status = $this->balance_due <= 0 ? 'paid' : ($this->amount_paid > 0 ? 'partial' : 'unpaid');
        $this->withoutPeriodValidation()->save();
    }

    public static function generateWaybillNumber($tenantId)
    {
        // Locked per-business sequence (R2).
        return \App\Support\DocumentNumber::next((int) $tenantId, static::class, 'waybill_number', 'WB-', 6);
    }

    public function isReleased()
    {
        return $this->released_at !== null;
    }

    public function canBeReleased()
    {
        return $this->status === 'paid' && ! $this->isReleased();
    }

    /**
     * Check there is enough free stock for the given invoice lines.
     *
     * Lines for the same item are added together. When $existing is given
     * (editing an invoice), the stock that invoice already holds counts as
     * available, because it is released before the new lines are reserved.
     * Inventory rows are locked, so call this inside a transaction.
     *
     * @param  array<int, array{item_id?: mixed, quantity: mixed}>  $lines
     * @return array<string, string> validation errors keyed by field
     */
    public static function stockShortages(array $lines, int $tenantId, ?self $existing = null): array
    {
        $requested = [];
        $firstLine = [];
        foreach ($lines as $index => $line) {
            if (empty($line['item_id'])) {
                continue;
            }
            $id = (int) $line['item_id'];
            $requested[$id] = ($requested[$id] ?? 0) + (float) $line['quantity'];
            $firstLine[$id] ??= $index;
        }

        $heldByThisInvoice = [];
        if ($existing && ! $existing->isReleased()) {
            foreach ($existing->items()->get() as $line) {
                if ($line->item_id) {
                    $heldByThisInvoice[$line->item_id] = ($heldByThisInvoice[$line->item_id] ?? 0) + (float) $line->quantity;
                }
            }
        }

        $errors = [];
        foreach ($requested as $itemId => $quantity) {
            $item = Item::find($itemId);
            if (! $item || ! $item->track_inventory || $item->type === 'service') {
                continue;
            }

            $inventory = Inventory::where('item_id', $itemId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            $available = ($inventory ? (float) $inventory->available_quantity : 0) + ($heldByThisInvoice[$itemId] ?? 0);

            if ($quantity - $available > 0.00001) {
                $errors["items.{$firstLine[$itemId]}.quantity"] =
                    "Insufficient stock for '{$item->name}'. Available: {$available}, Requested: {$quantity}";
            }
        }

        return $errors;
    }

    /**
     * Reserve stock for this invoice's lines (moves it from available to
     * reserved). Services and items that don't track stock are skipped.
     */
    public function reserveInventory(): void
    {
        if ($this->isReleased()) {
            return;
        }

        foreach ($this->items()->get() as $invoiceItem) {
            if (! $invoiceItem->item_id) {
                continue;
            }

            $item = Item::find($invoiceItem->item_id);
            if (! $item || ! $item->track_inventory || $item->type === 'service') {
                continue;
            }

            $inventory = Inventory::where('item_id', $invoiceItem->item_id)
                ->where('tenant_id', $this->tenant_id)
                ->first();

            if (! $inventory) {
                continue;
            }

            $inventory->reserved_quantity = ($inventory->reserved_quantity ?? 0) + $invoiceItem->quantity;
            $inventory->save();

            InventoryHistory::create([
                'tenant_id' => $this->tenant_id,
                'item_id' => $invoiceItem->item_id,
                'type' => 'reserved',
                'quantity' => $invoiceItem->quantity,
                'reference_type' => 'invoice',
                'reference_id' => $this->id,
                'notes' => "Reserved for Invoice #{$this->invoice_number}",
                'created_by' => auth()->id(),
            ]);
        }
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
                if (! $item || ! $item->track_inventory || $item->type === 'service') {
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

    /**
     * total = subtotal + tax_amount - discount_amount (see KeepsTotalsBalanced).
     */
    protected function documentTotalParts(): array
    {
        return [['subtotal', 'tax_amount'], ['discount_amount']];
    }

    /** Allowed status moves (Q3). */
    protected static function statusEnum(): string
    {
        return \App\Enums\InvoiceStatus::class;
    }
}
