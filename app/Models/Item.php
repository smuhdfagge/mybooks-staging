<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    const VALUATION_WEIGHTED_AVERAGE = 'weighted_average';

    const VALUATION_FIFO = 'fifo';

    const TRACKING_NONE = 'none';

    const TRACKING_BATCH = 'batch';

    const TRACKING_SERIAL = 'serial';

    protected $fillable = [
        'tenant_id',
        'category_id',
        'name',
        'sku',
        'description',
        'type',
        'unit',
        'purchase_uom_id',
        'sales_uom_id',
        'selling_price',
        'cost_price',
        'tax_rate',
        'is_taxable',
        'track_inventory',
        'valuation_method',
        'tracking_type',
        'reorder_level',
        'is_active',
        'image_path',
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

    /** @return BelongsTo<ItemCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    /** @return BelongsTo<TaxRate, $this> */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /** @return BelongsTo<TaxGroup, $this> */
    public function taxGroup(): BelongsTo
    {
        return $this->belongsTo(TaxGroup::class);
    }

    /**
     * Get the effective tax rate for this item
     */
    public function getEffectiveTaxRateAttribute(): ?float
    {
        if (! $this->is_taxable) {
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
            ->where(function ($q) {
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
        if (! $this->is_taxable) {
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

    /**
     * The item's stock across all its warehouses, as one record: quantity
     * and reserved are the sums, unit_cost the average (session 12). Read
     * only; stock is changed per warehouse (inventories()).
     *
     * @return HasOne<Inventory, $this>
     */
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class)
            ->select('inventories.item_id')
            ->selectRaw('MIN(inventories.id) as id, MIN(inventories.tenant_id) as tenant_id')
            ->selectRaw('SUM(inventories.quantity) as quantity, SUM(inventories.reserved_quantity) as reserved_quantity')
            ->selectRaw('CASE WHEN SUM(inventories.quantity) > 0 THEN SUM(inventories.quantity * inventories.unit_cost) / SUM(inventories.quantity) ELSE MAX(inventories.unit_cost) END as unit_cost')
            ->selectRaw('MIN(inventories.created_at) as created_at, MAX(inventories.updated_at) as updated_at')
            ->groupBy('inventories.item_id');
    }

    /** @return HasMany<Inventory, $this> stock per warehouse */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * SQL for the item's total quantity on hand across warehouses (or in
     * one), for filters such as "in stock" and "low stock".
     */
    public static function onHandSql(?int $warehouseId = null, string $column = 'quantity'): string
    {
        $where = $warehouseId ? ' AND inventories.warehouse_id = '.(int) $warehouseId : '';

        return "(SELECT COALESCE(SUM(inventories.{$column}), 0) FROM inventories WHERE inventories.item_id = items.id{$where})";
    }

    /** @return HasMany<InventoryHistory, $this> */
    public function inventoryHistory(): HasMany
    {
        return $this->hasMany(InventoryHistory::class);
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /** @return HasMany<BillItem, $this> */
    public function billItems(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    /** @return HasMany<InventoryLayer, $this> */
    public function inventoryLayers(): HasMany
    {
        return $this->hasMany(InventoryLayer::class);
    }

    /** @return HasMany<InventoryBatch, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    /** @return HasMany<SerialNumber, $this> */
    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class);
    }

    /** @return HasOne<BillOfMaterial, $this> */
    public function billOfMaterial(): HasOne
    {
        return $this->hasOne(BillOfMaterial::class);
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function purchaseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'purchase_uom_id');
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function salesUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'sales_uom_id');
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
