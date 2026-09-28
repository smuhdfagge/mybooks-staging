<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemController extends BaseApiController
{
    /**
     * Get all items
     */
    public function index(Request $request): JsonResponse
    {
        $query = Item::with(['category', 'inventory']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Filter by category
        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by inventory tracking
        if ($request->has('track_inventory')) {
            $query->where('track_inventory', $request->boolean('track_inventory'));
        }

        // Sorting
        [$sortBy, $sortOrder] = $this->validateSortParameters(
            $request,
            ['name', 'sku', 'type', 'selling_price', 'purchase_price', 'is_active', 'created_at', 'updated_at'],
            'name'
        );
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $items = $query->paginate($this->validatedPerPage($request));

        return $this->paginated($items->through(fn ($item) => new ItemResource($item)));
    }

    /**
     * Get a specific item
     */
    public function show(Item $item): JsonResponse
    {
        $item->load(['category', 'inventory']);

        return $this->success(new ItemResource($item));
    }

    /**
     * Create a new item
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'type' => 'required|in:product,service',
            'unit' => 'nullable|string|max:50',
            'category_id' => ['nullable', Rule::exists('item_categories', 'id')->where('tenant_id', $tenantId)],
            'selling_price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'is_taxable' => 'boolean',
            'track_inventory' => 'boolean',
            'reorder_level' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $item = Item::create($validated);
        $item->load(['category', 'inventory']);

        return $this->created(new ItemResource($item), 'Item created successfully');
    }

    /**
     * Update an item
     */
    public function update(Request $request, Item $item): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'sku' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:product,service',
            'unit' => 'nullable|string|max:50',
            'category_id' => ['nullable', Rule::exists('item_categories', 'id')->where('tenant_id', $tenantId)],
            'selling_price' => 'sometimes|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'is_taxable' => 'boolean',
            'track_inventory' => 'boolean',
            'reorder_level' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $item->update($validated);
        $item->load(['category', 'inventory']);

        return $this->success(new ItemResource($item), 'Item updated successfully');
    }

    /**
     * Delete an item
     */
    public function destroy(Item $item): JsonResponse
    {
        $item->delete();

        return $this->success(null, 'Item deleted successfully');
    }
}
