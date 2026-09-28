<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::withCount('inventories')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->paginate(20);

        return view('inventory.warehouses.index', compact('warehouses'));
    }

    public function create()
    {
        return view('inventory.warehouses.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:warehouses,code',
            'address' => 'nullable|string',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'is_default' => 'boolean',
        ]);

        $validated['tenant_id'] = auth()->user()->tenant_id;

        $warehouse = Warehouse::create($validated);

        if (! empty($validated['is_default'])) {
            $warehouse->setAsDefault();
        }

        return redirect()->route('warehouses.show', $warehouse)
            ->with('success', 'Warehouse created successfully.');
    }

    public function show(Warehouse $warehouse)
    {
        $inventories = $warehouse->inventories()
            ->with('item')
            ->where('quantity', '>', 0)
            ->paginate(20);

        return view('inventory.warehouses.show', compact('warehouse', 'inventories'));
    }

    public function edit(Warehouse $warehouse)
    {
        return view('inventory.warehouses.edit', compact('warehouse'));
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:warehouses,code,'.$warehouse->id,
            'address' => 'nullable|string',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $warehouse->update($validated);

        if (! empty($validated['is_default'])) {
            $warehouse->setAsDefault();
        }

        return redirect()->route('warehouses.show', $warehouse)
            ->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->is_default) {
            return redirect()->back()->with('error', 'Cannot delete the default warehouse.');
        }

        if ($warehouse->inventories()->where('quantity', '>', 0)->exists()) {
            return redirect()->back()->with('error', 'Cannot delete warehouse with stock. Transfer stock first.');
        }

        $warehouse->delete();

        return redirect()->route('warehouses.index')
            ->with('success', 'Warehouse deleted successfully.');
    }
}
