<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Warehouses (session 12): where a business keeps its stock. Every
 * business has at least one, and exactly one default; the default is used
 * whenever a document doesn't say. A warehouse holding stock can't be
 * deleted or switched off, and the last one can't be deleted.
 */
class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::orderByDesc('is_default')->orderBy('name')->paginate(20);

        // Stock value (quantity at each warehouse's average cost) and lines in stock.
        $totals = Inventory::query()
            ->selectRaw('warehouse_id, SUM(quantity * unit_cost) as stock_value, SUM(CASE WHEN quantity > 0 THEN 1 ELSE 0 END) as items_in_stock')
            ->groupBy('warehouse_id')
            ->get()
            ->keyBy('warehouse_id');

        // Goods shipped between warehouses and not yet received (session 13).
        $inTransitValue = array_sum(array_column(StockTransfer::inTransitByItem((int) auth()->user()->tenant_id), 'cost'));

        return view('inventory.warehouses.index', compact('warehouses', 'totals', 'inTransitValue'));
    }

    public function create()
    {
        return view('inventory.warehouses.create', ['warehouse' => new Warehouse(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $validated = $this->validated($request, $tenantId);

        $warehouse = DB::transaction(function () use ($validated, $request, $tenantId) {
            $warehouse = Warehouse::create($validated + [
                'tenant_id' => $tenantId,
                'is_active' => $request->boolean('is_active', true),
                'is_default' => false,
            ]);
            if ($request->boolean('is_default')) {
                $warehouse->setAsDefault();
            }

            return $warehouse;
        });

        return redirect()->route('warehouses.show', $warehouse)
            ->with('success', "Warehouse {$warehouse->name} added.");
    }

    public function show(Warehouse $warehouse)
    {
        $inventories = $warehouse->inventories()
            ->with('item')
            ->where(fn ($q) => $q->where('inventories.quantity', '!=', 0)->orWhere('inventories.reserved_quantity', '!=', 0))
            ->join('items', 'items.id', '=', 'inventories.item_id')
            ->orderBy('items.name')
            ->select('inventories.*')
            ->paginate(25);

        $stockValue = $warehouse->total_stock_value;

        // Goods on the road to or from here (session 13): in neither warehouse's stock yet.
        $inTransit = StockTransfer::moduleOn()
            ? StockTransfer::with(['fromWarehouse', 'toWarehouse', 'items.item'])
                ->where('status', StockTransfer::STATUS_IN_TRANSIT)
                ->where(fn ($q) => $q->where('from_warehouse_id', $warehouse->id)->orWhere('to_warehouse_id', $warehouse->id))
                ->orderBy('transfer_date')->get()
            : collect();

        return view('inventory.warehouses.show', compact('warehouse', 'inventories', 'stockValue', 'inTransit'));
    }

    public function edit(Warehouse $warehouse)
    {
        return view('inventory.warehouses.edit', compact('warehouse'));
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $validated = $this->validated($request, $warehouse->tenant_id, $warehouse);
        $active = $request->boolean('is_active');
        $makeDefault = $request->boolean('is_default');

        if ($warehouse->is_default && ! $makeDefault) {
            return back()->withInput()->withErrors(['is_default' => 'There must always be a default warehouse. Make another warehouse the default instead.']);
        }
        if (! $active && ($warehouse->is_default || $makeDefault)) {
            return back()->withInput()->withErrors(['is_active' => 'The default warehouse must stay in use.']);
        }
        if (! $active && $warehouse->is_active && $warehouse->holdsStock()) {
            return back()->withInput()->withErrors(['is_active' => "{$warehouse->name} still holds stock. Sell or move it before you stop using this warehouse."]);
        }

        DB::transaction(function () use ($warehouse, $validated, $active, $makeDefault) {
            $warehouse->update($validated + ['is_active' => $active]);
            if ($makeDefault && ! $warehouse->is_default) {
                $warehouse->setAsDefault();
            }
        });

        return redirect()->route('warehouses.show', $warehouse)
            ->with('success', "Warehouse {$warehouse->name} updated.");
    }

    public function destroy(Warehouse $warehouse)
    {
        if (Warehouse::count() <= 1) {
            return back()->with('error', 'This is your only warehouse, so it can\'t be deleted.');
        }
        if ($warehouse->is_default) {
            return back()->with('error', 'You can\'t delete the default warehouse. Make another warehouse the default first.');
        }
        if ($warehouse->holdsStock()) {
            return back()->with('error', "{$warehouse->name} still holds stock, so it can't be deleted.");
        }
        if ($warehouse->hasBeenUsed()) {
            return back()->with('error', "{$warehouse->name} has been used on documents, so it can't be deleted. Edit it and untick \"In use\" instead.");
        }

        DB::transaction(function () use ($warehouse) {
            $warehouse->inventories()->delete();
            $warehouse->delete();
        });

        return redirect()->route('warehouses.index')->with('success', 'Warehouse deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, int $tenantId, ?Warehouse $warehouse = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('warehouses', 'code')->where('tenant_id', $tenantId)->ignore($warehouse?->id)],
            'address' => ['nullable', 'string', 'max:1000'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ], [
            'code.unique' => 'Another warehouse already uses this code.',
        ]);
    }
}
