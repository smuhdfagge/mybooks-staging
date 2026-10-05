<?php

namespace App\Models;

use App\Services\StockValuationService;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bill of materials (session 14): what goes into one batch of a finished
 * item. Example: 40 bags of feed from 700 kg maize, 250 kg soya and 50 kg
 * premix, plus ₦20,000 labour. Component quantities are per batch and may
 * add a wastage %; extra costs (labour, power, packaging that isn't kept as
 * stock) are per batch too. A finished item can have more than one bill
 * (versions); the work is done by App\Actions\Assembly.
 */
class BillOfMaterial extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $table = 'bill_of_materials';

    protected $fillable = [
        'tenant_id',
        'item_id',
        'name',
        'version',
        'description',
        'output_quantity',
        'is_active',
    ];

    protected $casts = [
        'output_quantity' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return HasMany<BomItem, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(BomItem::class, 'bill_of_materials_id');
    }

    /** @return HasMany<BomCost, $this> */
    public function costs(): HasMany
    {
        return $this->hasMany(BomCost::class, 'bill_of_materials_id');
    }

    /**
     * The old relation looked for a bill_of_material_id column, which
     * doesn't exist, so it always failed (session 14).
     *
     * @return HasMany<AssemblyOrder, $this>
     */
    public function assemblyOrders(): HasMany
    {
        return $this->hasMany(AssemblyOrder::class, 'bill_of_materials_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** "Layer feed (v2)" */
    public function label(): string
    {
        return $this->name.($this->version ? " ({$this->version})" : '');
    }

    public function extraCostPerBatch(): float
    {
        return round((float) $this->costs->sum('amount'), 2);
    }

    /** How much of a component one unit of the finished item needs, wastage included. */
    public function needPerUnit(BomItem $line): float
    {
        $output = (float) $this->output_quantity;

        return $output > 0 ? $line->effective_quantity / $output : 0.0;
    }

    /**
     * What a batch should cost at today's component costs: each component at
     * its average cost over all warehouses (its cost price when none is in
     * stock), plus the extra costs.
     *
     * @return array{lines: array<int, array{line: BomItem, quantity: float, unit_cost: float, cost: float}>, components: float, extra: float, total: float, per_unit: float}
     */
    public function estimate(): array
    {
        $this->loadMissing(['components.item', 'costs']);
        $lines = [];
        $components = 0.0;
        foreach ($this->components as $line) {
            $unitCost = $line->item ? self::currentUnitCost($line->item) : 0.0;
            $quantity = round($line->effective_quantity, 4);
            $cost = round($quantity * $unitCost, 2);
            $lines[] = ['line' => $line, 'quantity' => $quantity, 'unit_cost' => $unitCost, 'cost' => $cost];
            $components += $cost;
        }
        $extra = $this->extraCostPerBatch();
        $total = round($components + $extra, 2);
        $output = (float) $this->output_quantity;

        return [
            'lines' => $lines,
            'components' => round($components, 2),
            'extra' => $extra,
            'total' => $total,
            'per_unit' => $output > 0 ? round($total / $output, 2) : 0.0,
        ];
    }

    /**
     * An item's average cost over its stock (in one warehouse, else all);
     * with none in stock, what it last came in at, else its cost price.
     */
    public static function currentUnitCost(Item $item, ?int $warehouseId = null): float
    {
        $valuation = app(StockValuationService::class)->getValuation($item, $warehouseId);
        if ($valuation['total_quantity'] > 0) {
            return (float) $valuation['average_cost'];
        }
        if ($warehouseId) {
            return self::currentUnitCost($item);
        }
        $last = InventoryLayer::withoutGlobalScopes()->where('tenant_id', $item->tenant_id)->where('item_id', $item->id)
            ->orderByDesc('received_date')->orderByDesc('id')->value('unit_cost');

        return $last !== null ? (float) $last : (float) ($item->cost_price ?? 0);
    }

    /**
     * How many finished units the free stock in one warehouse is enough for
     * (whole units), or null when the bill has no components.
     */
    public function maxBuildable(int $warehouseId): ?float
    {
        $this->loadMissing('components');
        $free = Inventory::withoutGlobalScopes()->where('tenant_id', $this->tenant_id)->where('warehouse_id', $warehouseId)
            ->whereIn('item_id', $this->components->pluck('item_id'))->get()
            ->mapWithKeys(fn (Inventory $row) => [(int) $row->item_id => max(0, (float) $row->quantity - (float) $row->reserved_quantity)]);

        // The same item on two lines counts together.
        $need = [];
        foreach ($this->components as $line) {
            $need[$line->item_id] = ($need[$line->item_id] ?? 0) + $this->needPerUnit($line);
        }

        $max = null;
        foreach ($need as $itemId => $perUnit) {
            if ($perUnit <= 0) {
                continue;
            }
            $can = floor(round(($free[$itemId] ?? 0) / $perUnit, 6));
            $max = $max === null ? $can : min($max, $can);
        }

        return $max;
    }

    /**
     * Whether making $finishedItemId from these components would go round in
     * a loop through other bills (A made from B, B made from A). Returns the
     * component that loops back, or null.
     *
     * @param  array<int, int>  $componentIds
     */
    public static function loopingComponent(int $tenantId, int $finishedItemId, array $componentIds, ?int $ignoreBomId = null): ?int
    {
        // item => items it is made from, over all this business's other bills.
        $madeFrom = [];
        $rows = BomItem::query()->join('bill_of_materials', 'bill_of_materials.id', '=', 'bom_items.bill_of_materials_id')
            ->where('bill_of_materials.tenant_id', $tenantId)
            ->when($ignoreBomId, fn ($q) => $q->where('bill_of_materials.id', '!=', $ignoreBomId))
            ->get(['bill_of_materials.item_id as made', 'bom_items.item_id as part']);
        foreach ($rows as $row) {
            $madeFrom[(int) $row->getAttribute('made')][(int) $row->getAttribute('part')] = true;
        }

        foreach ($componentIds as $start) {
            $seen = [];
            $stack = [(int) $start];
            while ($stack) {
                $current = array_pop($stack);
                if ($current === $finishedItemId) {
                    return (int) $start;
                }
                if (isset($seen[$current])) {
                    continue;
                }
                $seen[$current] = true;
                foreach (array_keys($madeFrom[$current] ?? []) as $next) {
                    $stack[] = $next;
                }
            }
        }

        return null;
    }
}
