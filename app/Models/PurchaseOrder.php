<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use App\Traits\ValidatesAccountingPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity, ValidatesAccountingPeriod;

    /** A bill has been raised for this order (finding N8). */
    public const STATUS_BILLED = 'billed';

    /** Statuses from which a bill can be raised. */
    public const BILLABLE = ['confirmed', 'partially_received', 'received'];

    protected $fillable = [
        'tenant_id',
        'vendor_id',
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
        'total_received_amount',
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
        'total_received_amount' => 'decimal:2',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
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

        $number = $last ? (int) substr($last->order_number, 3) + 1 : 1;

        return 'PO-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function hasUnreceivedItems(): bool
    {
        return $this->items()->whereColumn('quantity_received', '<', 'quantity')->exists();
    }

    public function updateReceivingStatus(): void
    {
        $totalQuantity = $this->items()->sum('quantity');
        $totalReceived = $this->items()->sum('quantity_received');

        $this->total_received_amount = $this->items()
            ->selectRaw('SUM(quantity_received * unit_price) as received_amount')
            ->value('received_amount') ?? 0;

        if ($totalReceived == 0) {
            $this->status = 'confirmed';
        } elseif ($totalReceived >= $totalQuantity) {
            $this->status = 'received';
        } else {
            $this->status = 'partially_received';
        }

        $this->save();
    }
}
