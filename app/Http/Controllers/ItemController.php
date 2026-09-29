<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Support\Facades\Storage;

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

    public function store(StoreItemRequest $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validated();

        $validated['tenant_id'] = $tenantId;

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('item-images', 'public');
        }
        unset($validated['image']);

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

    public function update(UpdateItemRequest $request, Item $item)
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('item-images', 'public');
        }
        unset($validated['image']);

        $item->update($validated);

        return redirect()->route('items.index')->with('success', 'Item updated successfully.');
    }

    public function destroy(Item $item)
    {
        $item->delete();

        return redirect()->route('items.index')->with('success', 'Item deleted successfully.');
    }
}
