<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
    public function store(StoreItemRequest $request): JsonResponse
    {
        $validated = $request->validated();
        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('item-images', 'public');
        }
        unset($validated['image']);

        $item = Item::create($validated);
        $item->load(['category', 'inventory']);

        return $this->created(new ItemResource($item), 'Item created successfully');
    }

    /**
     * Update an item
     */
    public function update(UpdateItemRequest $request, Item $item): JsonResponse
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
