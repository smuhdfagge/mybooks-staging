<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    public function index()
    {
        return view('items.index');
    }

    public function create()
    {
        $categories = ItemCategory::where('is_active', true)->get();
        return view('items.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'category_id' => ['nullable', Rule::exists('item_categories', 'id')->where('tenant_id', $tenantId)],
            'description' => 'nullable|string',
            'type' => 'required|in:product,service',
            'unit' => 'nullable|string|max:50',
            'selling_price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'is_taxable' => 'boolean',
            'track_inventory' => 'boolean',
            'reorder_level' => 'nullable|integer|min:0',
        ]);

        $validated['tenant_id'] = $tenantId;
        $item = Item::create($validated);

        return redirect()->route('items.index')->with('success', 'Item created successfully.');
    }

    public function show(Item $item)
    {
        return view('items.show', compact('item'));
    }

    public function edit(Item $item)
    {
        $categories = ItemCategory::where('is_active', true)->get();
        return view('items.edit', compact('item', 'categories'));
    }

    public function update(Request $request, Item $item)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'category_id' => ['nullable', Rule::exists('item_categories', 'id')->where('tenant_id', $tenantId)],
            'description' => 'nullable|string',
            'type' => 'required|in:product,service',
            'unit' => 'nullable|string|max:50',
            'selling_price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'is_taxable' => 'boolean',
            'track_inventory' => 'boolean',
            'reorder_level' => 'nullable|integer|min:0',
        ]);

        $item->update($validated);

        return redirect()->route('items.index')->with('success', 'Item updated successfully.');
    }

    public function destroy(Item $item)
    {
        $item->delete();
        return redirect()->route('items.index')->with('success', 'Item deleted successfully.');
    }
}
