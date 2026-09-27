<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;

class Quotation extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity;

    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CONVERTED = 'converted';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'quotation_number',
        'reference',
        'quotation_date',
        'expiry_date',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'discount_type',
        'total',
        'notes',
        'terms',
        'converted_to_so_id',
        'created_by',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'expiry_date' => 'date',
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
        return $this->hasMany(QuotationItem::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class, 'converted_to_so_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateNumber($tenantId): string
    {
        $last = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();

        $number = $last ? intval(substr($last->quotation_number, 4)) + 1 : 1;
        return 'QTN-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast() && !in_array($this->status, ['accepted', 'converted']);
    }

    /**
     * Convert this quotation to a Sales Order.
     */
    public function convertToSalesOrder(): SalesOrder
    {
        $tenantId = $this->tenant_id;

        $salesOrder = SalesOrder::create([
            'tenant_id' => $tenantId,
            'customer_id' => $this->customer_id,
            'order_number' => SalesOrder::generateNumber($tenantId),
            'reference' => "From {$this->quotation_number}",
            'order_date' => now(),
            'expected_date' => $this->expiry_date,
            'status' => 'draft',
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'discount_amount' => $this->discount_amount,
            'discount_type' => $this->discount_type,
            'total' => $this->total,
            'notes' => $this->notes,
            'terms' => $this->terms,
            'created_by' => auth()->id(),
        ]);

        foreach ($this->items as $item) {
            SalesOrderItem::create([
                'sales_order_id' => $salesOrder->id,
                'item_id' => $item->item_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'tax_rate' => $item->tax_rate,
                'tax_amount' => $item->tax_amount,
                'total' => $item->total,
            ]);
        }

        $this->update([
            'status' => self::STATUS_CONVERTED,
            'converted_to_so_id' => $salesOrder->id,
        ]);

        return $salesOrder;
    }
}
