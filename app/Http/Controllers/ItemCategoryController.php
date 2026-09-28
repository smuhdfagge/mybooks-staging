<?php

namespace App\Http\Controllers;

use App\Models\ItemCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemCategoryController extends Controller
{
    public function index()
    {
        return view('item-categories.index');
    }

    public function create()
    {
        $parentCategories = ItemCategory::whereNull('parent_id')->where('is_active', true)->get();

        return view('item-categories.create', compact('parentCategories'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'parent_id' => ['nullable', Rule::exists('item_categories', 'id')->where('tenant_id', $tenantId)],
            'description' => 'nullable|string',
        ]);

        ItemCategory::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('item-categories.index')->with('success', 'Category created.');
    }

    public function show(ItemCategory $itemCategory)
    {
        $itemCategory->load(['parent', 'children', 'items']);

        return view('item-categories.show', compact('itemCategory'));
    }

    public function edit(ItemCategory $itemCategory)
    {
        $parentCategories = ItemCategory::whereNull('parent_id')
            ->where('id', '!=', $itemCategory->id)
            ->where('is_active', true)
            ->get();

        return view('item-categories.edit', compact('itemCategory', 'parentCategories'));
    }

    public function update(Request $request, ItemCategory $itemCategory)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'parent_id' => ['nullable', Rule::exists('item_categories', 'id')->where('tenant_id', $tenantId)],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Prevent self-parenting
        if (isset($validated['parent_id']) && $validated['parent_id'] == $itemCategory->id) {
            return back()->withErrors(['parent_id' => 'Category cannot be its own parent.']);
        }

        $itemCategory->update([
            'name' => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('item-categories.index')->with('success', 'Category updated.');
    }

    public function destroy(ItemCategory $itemCategory)
    {
        if ($itemCategory->items()->exists()) {
            return redirect()->route('item-categories.index')->with('error', 'Cannot delete category with items.');
        }

        if ($itemCategory->children()->exists()) {
            return redirect()->route('item-categories.index')->with('error', 'Cannot delete category with subcategories.');
        }

        $itemCategory->delete();

        return redirect()->route('item-categories.index')->with('success', 'Category deleted.');
    }
}
