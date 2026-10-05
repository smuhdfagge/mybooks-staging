<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\InventoryLayer;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\StockValuationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index()
    {
        return view('inventory.index');
    }

    public function show(Item $item)
    {
        $inventory = $item->inventory;
        $history = $item->inventoryHistory()->with('warehouse')->latest()->paginate(20);
        $byWarehouse = $this->stockByWarehouse($item);

        return view('inventory.show', compact('item', 'inventory', 'history', 'byWarehouse'));
    }

    /**
     * The item's stock in each warehouse (session 12), for the item and
     * inventory pages. Empty when the business has only one warehouse.
     *
     * @return Collection<int, Inventory>
     */
    public static function stockByWarehouse(Item $item)
    {
        if (! Warehouse::moduleOn() || Warehouse::count() < 2) {
            return collect();
        }

        // Every warehouse in use, with nothing there shown as 0.
        $rows = $item->inventories()->get()->keyBy('warehouse_id');

        return Warehouse::orderByDesc('is_default')->orderBy('name')->get()
            ->filter(fn (Warehouse $w) => $w->is_active || $rows->has($w->id))
            ->map(fn (Warehouse $w) => ($rows->get($w->id) ?? new Inventory(['warehouse_id' => $w->id, 'quantity' => 0, 'reserved_quantity' => 0]))
                ->setRelation('warehouse', $w))
            ->values();
    }

    public function adjust(Request $request, Item $item)
    {
        $validated = $request->validate([
            'type' => 'required|in:in,out,adjustment',
            'quantity' => 'required|numeric|min:0.0001',
            'unit_cost' => 'nullable|numeric|min:0',
            'warehouse_id' => Warehouse::rule(auth()->user()->tenant_id),
            'batch_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $tenantId = auth()->user()->tenant_id;
        // Each warehouse is counted on its own (session 12); default warehouse if none is chosen.
        $warehouseId = Warehouse::resolveIdFor($tenantId, $validated['warehouse_id'] ?? null, true);
        $valuationService = app(StockValuationService::class);
        $quantity = (float) $validated['quantity'];
        $unitCost = (float) ($validated['unit_cost'] ?? ($item->cost_price ?? 0));

        DB::transaction(function () use ($validated, $item, $tenantId, $warehouseId, $valuationService, $quantity, $unitCost) {
            $inventory = $valuationService->stockRow($tenantId, $item->id, $warehouseId);
            if ($inventory->wasRecentlyCreated) {
                $inventory->unit_cost = $item->cost_price ?? 0;
            }

            $change = match ($validated['type']) {
                'in' => $quantity,
                'out' => -$quantity,
                default => round($quantity - (float) $inventory->quantity, 4),
            };

            // Stock can't go below what is reserved for invoices, or below zero.
            $free = (float) $inventory->quantity - (float) $inventory->reserved_quantity;
            if ($change < 0 && -$change - $free > 0.00001) {
                throw ValidationException::withMessages(['quantity' => 'Only '.rtrim(rtrim(number_format(max(0, $free), 4, '.', ''), '0'), '.').' is free in this warehouse, so you can\'t take out '.rtrim(rtrim(number_format(-$change, 4, '.', ''), '0'), '.').'.']);
            }

            if ($change > 0) {
                $valuationService->updateWeightedAverageCost($inventory, $change, $unitCost);
                InventoryLayer::create([
                    'tenant_id' => $tenantId,
                    'item_id' => $item->id,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $change,
                    'remaining_quantity' => $change,
                    'unit_cost' => $unitCost,
                    'reference_type' => 'adjustment',
                    'batch_number' => $validated['batch_number'] ?? null,
                    'received_date' => now()->toDateString(),
                ]);
            } elseif ($change < 0) {
                // Counting stock down used to leave the cost layers as they were.
                $valuationService->consumeStock($item, -$change, $warehouseId);
            }

            $inventory->quantity = round((float) $inventory->quantity + $change, 4);
            $inventory->save();

            InventoryHistory::create([
                'tenant_id' => $tenantId,
                'item_id' => $item->id,
                'warehouse_id' => $warehouseId,
                'type' => $validated['type'],
                'quantity' => $validated['type'] === 'adjustment' ? $change : $quantity,
                'notes' => $validated['type'] === 'adjustment'
                    ? trim('Counted: '.rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.').'. '.($validated['notes'] ?? ''))
                    : ($validated['notes'] ?? null),
                'created_by' => auth()->id(),
            ]);
        });

        $request->session()->put('warehouse.last', $warehouseId);

        return redirect()->back()->with('success', 'Inventory adjusted successfully.');
    }

    public function history(Request $request, Item $item)
    {
        // Movement history, for all warehouses or one (session 12).
        $warehouseId = $this->chosenWarehouse($request);
        $history = $item->inventoryHistory()->with(['createdBy', 'warehouse'])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->latest()->paginate(50)->withQueryString();
        $warehouses = Warehouse::moduleOn() ? Warehouse::orderByDesc('is_default')->orderBy('name')->get() : collect();

        return view('inventory.history', compact('item', 'history', 'warehouses', 'warehouseId'));
    }

    /** The warehouse a report is filtered by, if it is one of this business's. */
    private function chosenWarehouse(Request $request): ?int
    {
        $id = (int) $request->query('warehouse_id');

        return $id && Warehouse::whereKey($id)->exists() ? $id : null;
    }

    /**
     * Show stock valuation for a specific item.
     */
    public function valuation(Request $request, Item $item)
    {
        $warehouseId = $this->chosenWarehouse($request);
        $valuationService = app(StockValuationService::class);
        $valuation = $valuationService->getValuation($item, $warehouseId);

        $layers = InventoryLayer::where('tenant_id', auth()->user()->tenant_id)
            ->where('item_id', $item->id)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_date')
            ->paginate(50)->withQueryString();
        $warehouses = Warehouse::moduleOn() ? Warehouse::orderByDesc('is_default')->orderBy('name')->get() : collect();

        return view('inventory.valuation', compact('item', 'valuation', 'layers', 'warehouses', 'warehouseId'));
    }
}
