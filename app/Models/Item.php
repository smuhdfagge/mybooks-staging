<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;

class Item extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'name',
        'sku',
        'description',
        'type',
        'unit',
        'selling_price',
        'cost_price',
        'tax_rate',
        'is_taxable',
        'track_inventory',
        'reorder_level',
        'is_active',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'is_taxable' => 'boolean',
        'track_inventory' => 'boolean',
        'is_active' => 'boolean',
        'reorder_level' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function taxRate()
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function taxGroup()
    {
        return $this->belongsTo(TaxGroup::class);
    }

    /**
     * Get the effective tax rate for this item
     */
    public function getEffectiveTaxRateAttribute(): ?float
    {
        if (!$this->is_taxable) {
            return 0;
        }

        // Check for item-specific tax group
        if ($this->taxGroup) {
            return $this->taxGroup->combined_rate;
        }

        // Check for item-specific tax rate
        if ($this->taxRate) {
            return $this->taxRate->rate;
        }

        // Fall back to the old tax_rate column if set and > 0
        if ($this->tax_rate && $this->tax_rate > 0) {
            return $this->tax_rate;
        }

        // Fall back to tenant's default sales tax rate
        $tenant = Tenant::find($this->tenant_id);
        if ($tenant && $tenant->defaultSalesTax) {
            return $tenant->defaultSalesTax->rate;
        }

        // Last resort: find any active default sales tax for this tenant
        $defaultTax = TaxRate::where('tenant_id', $this->tenant_id)
            ->where('is_active', true)
            ->where(function($q) {
                $q->where('applies_to', 'sales')
                  ->orWhere('applies_to', 'both');
            })
            ->where('is_default', true)
            ->first();

        if ($defaultTax) {
            return $defaultTax->rate;
        }

        return 0;
    }

    /**
     * Calculate tax for a given amount
     */
    public function calculateTax(float $amount): float
    {
        if (!$this->is_taxable) {
            return 0;
        }

        if ($this->taxGroup) {
            return $this->taxGroup->calculateTotalTax($amount);
        }

        if ($this->taxRate) {
            return $this->taxRate->calculateTax($amount);
        }

        // Fall back to the old tax_rate column
        return $amount * (($this->tax_rate ?? 0) / 100);
    }

    public function inventory()
    {
        return $this->hasOne(Inventory::class);
    }

    public function inventoryHistory()
    {
        return $this->hasMany(InventoryHistory::class);
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function billItems()
    {
        return $this->hasMany(BillItem::class);
    }

    public function getCurrentStockAttribute()
    {
        return $this->inventory ? $this->inventory->quantity : 0;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
