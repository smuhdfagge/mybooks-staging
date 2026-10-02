<?php

namespace App\Models;

use App\Enums\DeliveryNoteStatus;
use App\Traits\BelongsToTenant;
use App\Traits\GuardsStatusTransitions;
use App\Traits\HasDocumentNumber;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class DeliveryNote extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;
    use GuardsStatusTransitions, HasDocumentNumber;

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
        'dispatched_at',
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
        'dispatched_at' => 'datetime',
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
     * Dispatch the goods: from now on they count as delivered on the sales
     * order (quantity_fulfilled and the order's status). No stock moves here;
     * see SaveDeliveryNote for the stock rule.
     */
    public function dispatch(): void
    {
        DB::transaction(function () {
            $this->update(['status' => DeliveryNoteStatus::Dispatched->value, 'dispatched_at' => now()]);
            $this->applyToOrder(1);
        });
    }

    /** The customer has the goods: record who received them. */
    public function markDelivered(string $receivedBy): void
    {
        $this->update([
            'status' => DeliveryNoteStatus::Delivered->value,
            'received_by' => $receivedBy,
            'received_at' => now(),
        ]);
    }

    /** Cancel the note. If it was dispatched, its quantities come off the order again. */
    public function cancel(): void
    {
        DB::transaction(function () {
            $wasCounted = in_array($this->status, DeliveryNoteStatus::countedValues(), true);
            $this->update(['status' => DeliveryNoteStatus::Cancelled->value]);
            if ($wasCounted) {
                $this->applyToOrder(-1);
            }
        });
    }

    /** Add (+1) or take off (-1) this note's quantities on its order lines. */
    protected function applyToOrder(int $sign): void
    {
        if (! $this->sales_order_id) {
            return;
        }

        foreach ($this->items()->get() as $line) {
            if (! $line->sales_order_item_id) {
                continue;
            }
            $orderLine = SalesOrderItem::whereKey($line->sales_order_item_id)->lockForUpdate()->first();
            if ($orderLine) {
                $orderLine->quantity_fulfilled = max(0, round((float) $orderLine->quantity_fulfilled + $sign * (float) $line->quantity_delivered, 2));
                $orderLine->save();
            }
        }

        $this->salesOrder()->first()?->updateFulfillmentStatus();
    }

    /** Allowed status moves. */
    protected static function statusEnum(): string
    {
        return DeliveryNoteStatus::class;
    }
}
