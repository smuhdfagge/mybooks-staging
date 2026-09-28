<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use App\Traits\ValidatesAccountingPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes, ValidatesAccountingPeriod;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'order_number',
        'reference',
        'order_date',
        'expected_date',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'discount_type',
        'total',
        'total_fulfilled_amount',
        'notes',
        'terms',
        'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'total_fulfilled_amount' => 'decimal:2',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<SalesOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<DeliveryNote, $this> */
    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    /** @return HasOne<Quotation, $this> */
    public function quotation(): HasOne
    {
        return $this->hasOne(Quotation::class, 'converted_to_so_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateNumber($tenantId)
    {
        $lastOrder = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();

        $number = $lastOrder ? intval(substr($lastOrder->order_number, 3)) + 1 : 1;

        return 'SO-'.str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Update fulfillment status based on item quantities.
     */
    public function updateFulfillmentStatus(): void
    {
        $this->load('items');
        $allFulfilled = true;
        $anyFulfilled = false;

        foreach ($this->items as $item) {
            if ($item->quantity_fulfilled >= $item->quantity) {
                $anyFulfilled = true;
            } else {
                $allFulfilled = false;
                if ($item->quantity_fulfilled > 0) {
                    $anyFulfilled = true;
                }
            }
        }

        if ($allFulfilled && $anyFulfilled) {
            $this->status = 'completed';
        } elseif ($anyFulfilled) {
            $this->status = 'processing';
        }

        // Calculate fulfilled amount
        $fulfilledAmount = $this->items->sum(function ($item) {
            $ratio = $item->quantity > 0 ? $item->quantity_fulfilled / $item->quantity : 0;

            return $item->total * min($ratio, 1);
        });
        $this->total_fulfilled_amount = $fulfilledAmount;
        $this->save();
    }

    /**
     * Check if any items have unfulfilled quantities.
     */
    public function hasUnfulfilledItems(): bool
    {
        return $this->items()->whereRaw('quantity_fulfilled < quantity')->exists();
    }
}
