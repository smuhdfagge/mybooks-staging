<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;

class Vendor extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'company_name',
        'tax_number',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'payment_terms',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tax_number' => 'encrypted',
    ];

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function payments()
    {
        return $this->hasMany(PaymentMade::class);
    }

    public function getTotalPurchasesAttribute()
    {
        return $this->bills()->sum('total');
    }

    public function getOutstandingBalanceAttribute()
    {
        return $this->bills()->where('status', '!=', 'paid')->sum('balance_due');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
