<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasDocumentNumber;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryNote extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;
    use HasDocumentNumber;

    const STATUS_DRAFT = 'draft';

    const STATUS_DISPATCHED = 'dispatched';

    const STATUS_IN_TRANSIT = 'in_transit';

    const STATUS_DELIVERED = 'delivered';

    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'sales_order_id',
        'invoice_id',
        'customer_id',
        'delivery_number',
        'delivery_date',
        'status',
        'shipping_method',
        'tracking_number',
        'shipping_address',
        'notes',
        'received_by',
        'received_at',
        'created_by',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'received_at' => 'datetime',
    ];

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

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return HasMany<DeliveryNoteItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array{0: string, 1: string, 2: int} */
    protected static function documentNumberFormat(): array
    {
        return ['delivery_number', 'DN-', 6];
    }

    /**
     * Mark delivery as dispatched.
     */
    public function dispatch(): bool
    {
        if ($this->status !== self::STATUS_DRAFT) {
            return false;
        }

        $this->update(['status' => self::STATUS_DISPATCHED]);

        return true;
    }

    /**
     * Confirm delivery — updates sales order fulfillment and optionally deducts inventory.
     */
    public function confirmDelivery(string $receivedBy): bool
    {
        if (in_array($this->status, [self::STATUS_DELIVERED, self::STATUS_CANCELLED])) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_DELIVERED,
            'received_by' => $receivedBy,
            'received_at' => now(),
        ]);

        // Update sales order fulfilled quantities
        if ($this->sales_order_id) {
            $salesOrder = $this->salesOrder;
            foreach ($this->items as $dnItem) {
                if ($dnItem->item_id) {
                    $soItem = $salesOrder->items()
                        ->where('item_id', $dnItem->item_id)
                        ->first();
                    if ($soItem) {
                        $soItem->increment('quantity_fulfilled', $dnItem->quantity_delivered);
                    }
                }
            }
            $salesOrder->updateFulfillmentStatus();
        }

        // Deduct inventory
        $this->deductInventory();

        return true;
    }

    /**
     * Deduct inventory based on delivered quantities.
     */
    protected function deductInventory(): void
    {
        $tenantId = $this->tenant_id;

        foreach ($this->items as $dnItem) {
            if (! $dnItem->item_id || $dnItem->quantity_delivered <= 0) {
                continue;
            }

            $item = Item::find($dnItem->item_id);
            if (! $item || ! $item->track_inventory || $item->type === 'service') {
                continue;
            }

            $inventory = Inventory::where('item_id', $dnItem->item_id)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($inventory) {
                $inventory->quantity = max(0, $inventory->quantity - $dnItem->quantity_delivered);
                $inventory->save();

                InventoryHistory::create([
                    'tenant_id' => $tenantId,
                    'item_id' => $dnItem->item_id,
                    'type' => 'out',
                    'quantity' => -$dnItem->quantity_delivered,
                    'reference_type' => 'delivery_note',
                    'reference_id' => $this->id,
                    'notes' => "Delivered via DN #{$this->delivery_number}",
                    'created_by' => auth()->id(),
                ]);
            }
        }
    }
}
