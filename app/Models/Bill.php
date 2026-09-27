<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use App\Traits\ValidatesAccountingPeriod;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;

class Bill extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity, ValidatesAccountingPeriod;

    protected $fillable = [
        'tenant_id',
        'vendor_id',
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

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items()
    {
        return $this->hasMany(BillItem::class);
    }

    public function payments()
    {
        return $this->hasMany(PaymentMade::class);
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
     * Create or update journal entry for this bill
     */
    public function createJournalEntry(): ?Journal
    {
        $journalService = app(JournalService::class);
        return $journalService->createBillJournal($this);
    }

    protected static function booted()
    {
        // Create journal entry when bill is created or updated (skip drafts)
        static::saved(function ($bill) {
            if ($bill->total > 0 && $bill->status !== 'draft') {
                $bill->createJournalEntry();
            }
        });

        // Delete journal entry when bill is deleted
        static::deleting(function ($bill) {
            $journalService = app(JournalService::class);
            $journalService->deleteJournalForTransaction(Bill::class, $bill->id, $bill->tenant_id);
        });
    }

    public static function generateNumber($tenantId)
    {
        $lastBill = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();
        
        $number = $lastBill ? intval(substr($lastBill->bill_number, 5)) + 1 : 1;
        return 'BILL-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function updateBalances()
    {
        $previousStatus = $this->status;
        
        $this->amount_paid = $this->payments()->sum('amount');
        $this->balance_due = $this->total - $this->amount_paid;
        $this->status = $this->balance_due <= 0 ? 'paid' : ($this->amount_paid > 0 ? 'partial' : 'unpaid');
        $this->withoutPeriodValidation()->save();
        
        // Update inventory when bill becomes paid (and hasn't been updated yet)
        if ($this->status === 'paid' && $previousStatus !== 'paid' && !$this->inventory_updated_at) {
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
                        
                        // Add quantity
                        $previousQty = $inventory->quantity;
                        $inventory->quantity += $billItem->quantity;
                        $inventory->save();
                        
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
}
