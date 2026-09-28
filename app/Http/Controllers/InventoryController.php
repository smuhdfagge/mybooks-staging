<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\InventoryLayer;
use App\Models\Item;
use App\Services\StockValuationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index()
    {
        return view('inventory.index');
    }

    public function show(Item $item)
    {
        $inventory = $item->inventory;
        $history = $item->inventoryHistory()->latest()->paginate(20);

        return view('inventory.show', compact('item', 'inventory', 'history'));
    }

    public function adjust(Request $request, Item $item)
    {
        $validated = $request->validate([
            'type' => 'required|in:in,out,adjustment',
            'quantity' => 'required|numeric|min:0.0001',
            'unit_cost' => 'nullable|numeric|min:0',
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'batch_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $warehouseId = $validated['warehouse_id'] ?? null;

        $inventory = Inventory::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'item_id' => $item->id,
                'warehouse_id' => $warehouseId,
            ],
            ['quantity' => 0, 'reserved_quantity' => 0, 'unit_cost' => $item->cost_price ?? 0]
        );

        $unitCost = $validated['unit_cost'] ?? ($item->cost_price ?? 0);

        if ($validated['type'] === 'in') {
            // Update WAC
            $valuationService = app(StockValuationService::class);
            $valuationService->updateWeightedAverageCost($inventory, $validated['quantity'], $unitCost);

            $inventory->quantity += $validated['quantity'];
            $inventory->save();

            // Create inventory layer
            InventoryLayer::create([
                'tenant_id' => $tenantId,
                'item_id' => $item->id,
                'warehouse_id' => $warehouseId,
                'quantity' => $validated['quantity'],
                'remaining_quantity' => $validated['quantity'],
                'unit_cost' => $unitCost,
                'reference_type' => 'adjustment',
                'batch_number' => $validated['batch_number'] ?? null,
                'received_date' => now()->toDateString(),
            ]);
        } elseif ($validated['type'] === 'out') {
            $inventory->quantity -= $validated['quantity'];
            $inventory->save();

            // Consume from layers
            $valuationService = app(StockValuationService::class);
            $valuationService->consumeStock($item, $validated['quantity'], $warehouseId);
        } else {
            // Full adjustment — set to specific value
            $difference = $validated['quantity'] - (float) $inventory->quantity;
            $inventory->quantity = $validated['quantity'];
            $inventory->save();

            if ($difference > 0) {
                InventoryLayer::create([
                    'tenant_id' => $tenantId,
                    'item_id' => $item->id,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $difference,
                    'remaining_quantity' => $difference,
                    'unit_cost' => $unitCost,
                    'reference_type' => 'adjustment',
                    'batch_number' => $validated['batch_number'] ?? null,
                    'received_date' => now()->toDateString(),
                ]);
            }
        }

        // Record history
        InventoryHistory::create([
            'tenant_id' => $tenantId,
            'item_id' => $item->id,
            'type' => $validated['type'],
            'quantity' => $validated['quantity'],
            'notes' => $validated['notes'],
            'created_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Inventory adjusted successfully.');
    }

    public function history(Item $item)
    {
        $history = $item->inventoryHistory()->with('createdBy')->latest()->paginate(50);

        return view('inventory.history', compact('item', 'history'));
    }

    /**
     * Show stock valuation for a specific item.
     */
    public function valuation(Item $item)
    {
        $valuationService = app(StockValuationService::class);
        $valuation = $valuationService->getValuation($item);

        $layers = InventoryLayer::where('tenant_id', auth()->user()->tenant_id)
            ->where('item_id', $item->id)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_date')
            ->paginate(50);

        return view('inventory.valuation', compact('item', 'valuation', 'layers'));
    }
}
