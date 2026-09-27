<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\Item;
use Illuminate\Http\Request;

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
            'notes' => 'nullable|string',
        ]);

        $inventory = $item->inventory ?? Inventory::create([
            'tenant_id' => auth()->user()->tenant_id,
            'item_id' => $item->id,
            'quantity' => 0,
        ]);

        if ($validated['type'] === 'in') {
            $inventory->quantity += $validated['quantity'];
        } elseif ($validated['type'] === 'out') {
            $inventory->quantity -= $validated['quantity'];
        } else {
            $inventory->quantity = $validated['quantity'];
        }

        $inventory->save();

        // Record history
        InventoryHistory::create([
            'tenant_id' => auth()->user()->tenant_id,
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
}
