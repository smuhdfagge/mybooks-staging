<?php

namespace App\Models;

use App\Events\BillDeleting;
use App\Events\BillSaved;
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
use Illuminate\Support\Facades\DB;

class Bill extends Model
{
    use \App\Traits\KeepsTotalsBalanced, BelongsToTenant, HasFactory, LogsActivity, SoftDeletes, ValidatesAccountingPeriod;

    protected $fillable = [
        'tenant_id',
        'vendor_id',
        'purchase_order_id',
        'bill_number',
        'vendor_bill_number',
        'bill_date',
        'due_date',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'amount_paid',
        'balance_due',
        'notes',
        'created_by',
        'inventory_updated_at',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'inventory_updated_at' => 'datetime',
    ];

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return HasMany<BillItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    /** @return HasMany<PaymentMade, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(PaymentMade::class);
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
     * Create or update journal entry for this bill
     */
    public function createJournalEntry(): ?Journal
    {
        $journalService = app(JournalService::class);

        return $journalService->createBillJournal($this);
    }

    protected static function booted()
    {
        static::saved(function ($bill) {
            if ($bill->total > 0 && $bill->status !== 'draft') {
                BillSaved::dispatch($bill);
            }
        });

        static::deleting(function ($bill) {
            BillDeleting::dispatch($bill);
        });
    }

    public static function generateNumber($tenantId)
    {
        $lastBill = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();

        $number = $lastBill ? intval(substr($lastBill->bill_number, 5)) + 1 : 1;

        return 'BILL-'.str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Post (or re-post) the bill once its lines are saved: the journal splits
     * goods and expenses by line, and goods enter stock (A21). Saving the
     * bill before its lines exist posted everything to Inventory.
     */
    public function postWithLines(): void
    {
        if ((float) $this->total > 0 && $this->status !== 'draft') {
            BillSaved::dispatch($this->fresh());
        }
    }

    public function updateBalances()
    {

        $this->amount_paid = $this->payments()->sum('amount');
        $this->balance_due = $this->total - $this->amount_paid;
        $this->status = $this->balance_due <= 0 ? 'paid' : ($this->amount_paid > 0 ? 'partial' : 'unpaid');
        $this->withoutPeriodValidation()->save();

        // Stock is received when the bill is posted (A21); this only catches
        // bills posted before that change.
        if (! in_array($this->status, ['draft', 'cancelled'], true) && ! $this->inventory_updated_at && $this->items()->exists()) {
            $this->updateInventory();
        }
    }

    public function updateInventory()
    {
        DB::beginTransaction();
        try {
            foreach ($this->items as $billItem) {
                if ($billItem->item_id) {
                    $item = Item::find($billItem->item_id);
                    if ($item && $item->track_inventory) {
                        // Find or create inventory record
                        $inventory = Inventory::firstOrCreate(
                            [
                                'tenant_id' => $this->tenant_id,
                                'item_id' => $billItem->item_id,
                            ],
                            [
                                'quantity' => 0,
                                'reserved_quantity' => 0,
                            ]
                        );

                        // Update weighted average cost
                        // Cost per unit excludes VAT: the tax goes to input tax in the
                        // journal, so including it here would put VAT into COGS.
                        $netLine = ($billItem->total ?? ($billItem->unit_price * $billItem->quantity))
                            - (float) ($billItem->tax_amount ?? 0);
                        $unitCost = $billItem->quantity > 0
                            ? round($netLine / $billItem->quantity, 4)
                            : ($item->cost_price ?? 0);
                        $valuationService = app(\App\Services\StockValuationService::class);
                        $valuationService->updateWeightedAverageCost($inventory, $billItem->quantity, $unitCost);

                        // Add quantity
                        $inventory->quantity += $billItem->quantity;
                        $inventory->save();

                        // Create inventory layer for FIFO tracking
                        InventoryLayer::create([
                            'tenant_id' => $this->tenant_id,
                            'item_id' => $billItem->item_id,
                            'warehouse_id' => $inventory->warehouse_id,
                            'quantity' => $billItem->quantity,
                            'remaining_quantity' => $billItem->quantity,
                            'unit_cost' => $unitCost,
                            'reference_type' => 'bill',
                            'reference_id' => $this->id,
                            'received_date' => optional($this->bill_date)->toDateString() ?? now()->toDateString(),
                        ]);

                        // Record inventory history
                        InventoryHistory::create([
                            'tenant_id' => $this->tenant_id,
                            'item_id' => $billItem->item_id,
                            'type' => 'in',
                            'quantity' => $billItem->quantity,
                            'reference_type' => 'bill',
                            'reference_id' => $this->id,
                            'notes' => "Stock received from Bill #{$this->bill_number}",
                            'created_by' => auth()->id(),
                        ]);
                    }
                }
            }

            // Mark inventory as updated
            $this->inventory_updated_at = now();
            $this->withoutPeriodValidation()->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * total = subtotal + tax_amount - discount_amount (see KeepsTotalsBalanced).
     */
    protected function documentTotalParts(): array
    {
        return [['subtotal', 'tax_amount'], ['discount_amount']];
    }
}
