<?php

namespace App\Http\Controllers;

use App\Models\AssemblyOrder;
use App\Models\BillOfMaterial;
use App\Models\BomItem;
use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillOfMaterialController extends Controller
{
    public function index()
    {
        $boms = BillOfMaterial::with(['item', 'components.item'])
            ->latest()
            ->paginate(20);

        return view('inventory.bom.index', compact('boms'));
    }

    public function create()
    {
        $items = Item::where('track_inventory', true)->active()->orderBy('name')->get();

        return view('inventory.bom.create', compact('items'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id|unique:bill_of_materials,item_id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'output_quantity' => 'required|numeric|min:0.0001',
            'components' => 'required|array|min:1',
            'components.*.item_id' => 'required|exists:items,id',
            'components.*.quantity' => 'required|numeric|min:0.0001',
            'components.*.waste_percentage' => 'nullable|numeric|min:0|max:100',
            'components.*.notes' => 'nullable|string',
        ]);

        $tenantId = auth()->user()->tenant_id;

        // Prevent circular reference
        foreach ($validated['components'] as $component) {
            if ($component['item_id'] == $validated['item_id']) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'A product cannot be a component of itself.');
            }
        }

        $bom = DB::transaction(function () use ($validated, $tenantId) {
            $bom = BillOfMaterial::create([
                'tenant_id' => $tenantId,
                'item_id' => $validated['item_id'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'output_quantity' => $validated['output_quantity'],
            ]);

            foreach ($validated['components'] as $component) {
                $bom->components()->create([
                    'item_id' => $component['item_id'],
                    'quantity' => $component['quantity'],
                    'waste_percentage' => $component['waste_percentage'] ?? 0,
                    'notes' => $component['notes'] ?? null,
                ]);
            }

            return $bom;
        });

        return redirect()->route('bill-of-materials.show', $bom)
            ->with('success', 'Bill of Materials created successfully.');
    }

    public function show(BillOfMaterial $billOfMaterial)
    {
        $billOfMaterial->load(['item', 'components.item', 'assemblyOrders']);

        return view('inventory.bom.show', compact('billOfMaterial'));
    }

    public function edit(BillOfMaterial $billOfMaterial)
    {
        $billOfMaterial->load('components');
        $items = Item::where('track_inventory', true)->active()->orderBy('name')->get();

        return view('inventory.bom.edit', compact('billOfMaterial', 'items'));
    }

    public function update(Request $request, BillOfMaterial $billOfMaterial)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'output_quantity' => 'required|numeric|min:0.0001',
            'is_active' => 'boolean',
            'components' => 'required|array|min:1',
            'components.*.item_id' => 'required|exists:items,id',
            'components.*.quantity' => 'required|numeric|min:0.0001',
            'components.*.waste_percentage' => 'nullable|numeric|min:0|max:100',
            'components.*.notes' => 'nullable|string',
        ]);

        foreach ($validated['components'] as $component) {
            if ($component['item_id'] == $billOfMaterial->item_id) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'A product cannot be a component of itself.');
            }
        }

        DB::transaction(function () use ($validated, $billOfMaterial) {
            $billOfMaterial->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'output_quantity' => $validated['output_quantity'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $billOfMaterial->components()->delete();

            foreach ($validated['components'] as $component) {
                $billOfMaterial->components()->create([
                    'item_id' => $component['item_id'],
                    'quantity' => $component['quantity'],
                    'waste_percentage' => $component['waste_percentage'] ?? 0,
                    'notes' => $component['notes'] ?? null,
                ]);
            }
        });

        return redirect()->route('bill-of-materials.show', $billOfMaterial)
            ->with('success', 'Bill of Materials updated successfully.');
    }

    public function destroy(BillOfMaterial $billOfMaterial)
    {
        if ($billOfMaterial->assemblyOrders()->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot delete BOM with existing assembly orders.');
        }

        $billOfMaterial->components()->delete();
        $billOfMaterial->delete();

        return redirect()->route('bill-of-materials.index')
            ->with('success', 'Bill of Materials deleted successfully.');
    }

    /**
     * Create a new assembly order from this BOM.
     */
    public function createAssemblyOrder(BillOfMaterial $billOfMaterial)
    {
        $warehouses = Warehouse::active()->orderBy('name')->get();

        return view('inventory.assembly.create', compact('billOfMaterial', 'warehouses'));
    }

    public function storeAssemblyOrder(Request $request, BillOfMaterial $billOfMaterial)
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.0001',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'notes' => 'nullable|string',
        ]);

        $tenantId = auth()->user()->tenant_id;

        $order = AssemblyOrder::create([
            'tenant_id' => $tenantId,
            'order_number' => AssemblyOrder::generateNumber($tenantId),
            'bill_of_materials_id' => $billOfMaterial->id,
            'warehouse_id' => $validated['warehouse_id'] ?? null,
            'quantity' => $validated['quantity'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('assembly-orders.show', $order)
            ->with('success', 'Assembly order created.');
    }

    public function showAssemblyOrder(AssemblyOrder $assemblyOrder)
    {
        $assemblyOrder->load(['billOfMaterial.item', 'billOfMaterial.components.item', 'warehouse', 'createdBy']);

        return view('inventory.assembly.show', compact('assemblyOrder'));
    }

    public function completeAssemblyOrder(AssemblyOrder $assemblyOrder)
    {
        $assemblyOrder->complete();

        return redirect()->route('assembly-orders.show', $assemblyOrder)
            ->with('success', 'Assembly order completed. Finished goods added to inventory.');
    }

    public function assemblyOrders()
    {
        $orders = AssemblyOrder::with(['billOfMaterial.item', 'warehouse', 'createdBy'])
            ->latest()
            ->paginate(20);

        return view('inventory.assembly.index', compact('orders'));
    }
}
