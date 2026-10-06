<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\AdjustStock;
use App\Models\ChartOfAccount;
use App\Models\Inventory;
use App\Models\InventoryLayer;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\AccountCodeService;
use App\Services\StockValuationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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
        // Accounts the adjustment can post against instead of Stock Losses (F1).
        $tenantId = auth()->user()->tenant_id;
        $inventoryCode = AccountCodeService::resolve($tenantId, 'inventory');
        $accounts = ChartOfAccount::where('is_active', true)->where('account_code', '!=', $inventoryCode)
            ->orderBy('account_code')->get(['id', 'account_code', 'name']);
        $stockLosses = AccountCodeService::resolve($tenantId, 'stock_losses');

        return view('inventory.show', compact('item', 'inventory', 'history', 'byWarehouse', 'accounts', 'stockLosses'));
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

    public function adjust(Request $request, Item $item, AdjustStock $adjust)
    {
        $tenantId = auth()->user()->tenant_id;
        $validated = $request->validate([
            'type' => 'required|in:in,out,adjustment',
            'quantity' => 'required|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'warehouse_id' => Warehouse::rule($tenantId),
            'account_id' => 'nullable|integer',
            'date' => 'nullable|date|before_or_equal:today',
            'batch_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        // Each warehouse is counted on its own (session 12); default warehouse if none is chosen.
        $warehouseId = Warehouse::resolveIdFor($tenantId, $validated['warehouse_id'] ?? null, true);
        $mode = $validated['type'] === 'adjustment' ? AdjustStock::SET : $validated['type'];

        // Posts Dr/Cr Stock Losses against Inventory at cost (F1).
        $adjust->handle($item, $mode, (float) $validated['quantity'],
            warehouseId: $warehouseId,
            unitCost: isset($validated['unit_cost']) && $validated['unit_cost'] !== '' ? (float) $validated['unit_cost'] : null,
            reason: $validated['notes'] ?? null,
            accountId: ! empty($validated['account_id']) ? (int) $validated['account_id'] : null,
            date: $validated['date'] ?? null,
            batchNumber: $validated['batch_number'] ?? null,
            label: $mode === AdjustStock::SET ? 'Stock count' : 'Stock adjustment');

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
