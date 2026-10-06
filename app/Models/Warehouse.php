<?php

namespace App\Models;

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Warehouse extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'address',
        'contact_person',
        'phone',
        'email',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<Inventory, $this> */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /** @return HasMany<InventoryLayer, $this> */
    public function inventoryLayers(): HasMany
    {
        return $this->hasMany(InventoryLayer::class);
    }

    /** @return HasMany<StockTransfer, $this> */
    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'to_warehouse_id');
    }

    /** @return HasMany<StockTransfer, $this> */
    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'from_warehouse_id');
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the default warehouse for a tenant.
     */
    public static function getDefault(int $tenantId): ?self
    {
        return static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first();
    }

    /**
     * The business's default warehouse id (session 12). Every business has
     * one; it is made ("Main warehouse") if it is somehow missing.
     */
    public static function defaultIdFor(int $tenantId): int
    {
        $id = static::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('is_default', true)->value('id');

        return $id ? (int) $id : static::ensureDefaultFor($tenantId)->id;
    }

    /** Give a business its default warehouse if it has none (new businesses). */
    public static function ensureDefaultFor(int $tenantId): self
    {
        if ($default = static::getDefault($tenantId)) {
            return $default;
        }

        $first = static::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('id')->first();
        if ($first) {
            $first->forceFill(['is_default' => true, 'is_active' => true])->saveQuietly();

            return $first;
        }

        $warehouse = new self([
            'tenant_id' => $tenantId,
            'name' => 'Main warehouse',
            'code' => 'MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);
        $warehouse->skipTenantGuard = true;
        $warehouse->saveQuietly();

        return $warehouse;
    }

    /**
     * The warehouse a stock document uses: the one asked for, which must
     * belong to this business, or the default. With the module switched
     * off everything uses the default warehouse.
     */
    public static function resolveIdFor(int $tenantId, mixed $requested, bool $mustBeActive = false): int
    {
        if ($requested === null || $requested === '' || ! static::moduleOn()) {
            return static::defaultIdFor($tenantId);
        }

        $warehouse = static::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey((int) $requested)->first();
        if (! $warehouse) {
            throw ValidationException::withMessages(['warehouse_id' => 'Choose one of your own warehouses.']);
        }
        if ($mustBeActive && ! $warehouse->is_active) {
            throw ValidationException::withMessages(['warehouse_id' => "Warehouse {$warehouse->name} is not in use any more."]);
        }

        return (int) $warehouse->id;
    }

    /**
     * Validation for a document's optional warehouse_id: one of this
     * business's warehouses (tenant isolation).
     *
     * @return array<int, mixed>
     */
    public static function rule(int $tenantId): array
    {
        return ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)];
    }

    /**
     * A document's warehouse name to show on its page, only when the
     * business has more than one warehouse (otherwise it says nothing new).
     */
    public static function nameIfMany(mixed $id): ?string
    {
        if (! $id || ! static::moduleOn() || static::count() < 2) {
            return null;
        }

        return static::whereKey((int) $id)->value('name');
    }

    /** Remember the warehouse a user picked, to preselect it next time. */
    public static function rememberChoice(mixed $id): void
    {
        if ($id && static::moduleOn()) {
            session()->put('warehouse.last', (int) $id);
        }
    }

    public static function moduleOn(): bool
    {
        return EnsureFeatureEnabled::enabled('warehouses');
    }

    /**
     * Active warehouses to choose from on a form, default first. Empty when
     * there is nothing to choose (one warehouse, or the module is off), so
     * the picker is hidden.
     *
     * @return Collection<int, self>
     */
    public static function choicesFor(int $tenantId): Collection
    {
        if (! static::moduleOn()) {
            return new Collection;
        }

        $list = static::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('is_active', true)
            ->orderByDesc('is_default')->orderBy('name')->get();

        return $list->count() > 1 ? $list : new Collection;
    }

    /** Whether any stock (on hand, in cost layers or in transit) is in this warehouse. */
    public function holdsStock(): bool
    {
        return Inventory::withoutGlobalScopes()->where('warehouse_id', $this->id)
            ->where(fn ($q) => $q->where('quantity', '>', 0.00001)->orWhere('quantity', '<', -0.00001)->orWhere('reserved_quantity', '>', 0.00001))
            ->exists()
            || InventoryLayer::withoutGlobalScopes()->where('warehouse_id', $this->id)->where('remaining_quantity', '>', 0.00001)->exists()
            // Goods on the road to or from here (session 13).
            || StockTransfer::withoutGlobalScopes()->where('status', StockTransfer::STATUS_IN_TRANSIT)
                ->where(fn ($q) => $q->where('from_warehouse_id', $this->id)->orWhere('to_warehouse_id', $this->id))->exists();
    }

    /** Whether documents or stock history point at this warehouse. */
    public function hasBeenUsed(): bool
    {
        foreach (['inventory_histories', 'invoices', 'sales_receipts', 'bills', 'credit_notes', 'vendor_credits', 'delivery_notes'] as $table) {
            if (DB::table($table)->where('warehouse_id', $this->id)->exists()) {
                return true;
            }
        }

        // Stock transfers (session 13); deleting the warehouse would delete them.
        // Assembly orders (session 14) would lose where their stock went.
        return DB::table('stock_transfers')->where('from_warehouse_id', $this->id)->orWhere('to_warehouse_id', $this->id)->exists()
            || DB::table('assembly_orders')->where('warehouse_id', $this->id)->orWhere('to_warehouse_id', $this->id)->exists();
    }

    /**
     * Set this warehouse as the default (unsets others).
     */
    public function setAsDefault(): void
    {
        DB::transaction(function () {
            static::withoutGlobalScopes()->where('tenant_id', $this->tenant_id)
                ->where('id', '!=', $this->id)
                ->update(['is_default' => false]);

            $this->update(['is_default' => true, 'is_active' => true]);
        });
    }

    /**
     * Value of the stock on hand here: quantity at this warehouse's average cost.
     */
    public function getTotalStockValueAttribute(): float
    {
        return (float) ($this->inventories()
            ->selectRaw('SUM(quantity * unit_cost) as total')
            ->value('total') ?? 0);
    }
}
