<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use App\Traits\ValidatesAccountingPeriod;

class SalesOrder extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity, ValidatesAccountingPeriod;

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
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function createdBy()
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
        return 'SO-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}
