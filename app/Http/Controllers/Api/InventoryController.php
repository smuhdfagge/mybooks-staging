<?php

namespace App\Http\Controllers\Api;

use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Http\Resources\InventoryResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InventoryController extends BaseApiController
{
    /**
     * Get all inventory items
     */
    public function index(Request $request): JsonResponse
    {
        $query = Inventory::with(['item.category']);

        // Search
        if ($search = $request->input('search')) {
            $query->whereHas('item', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Filter low stock
        if ($request->boolean('low_stock')) {
            $query->whereHas('item', function ($q) {
                $q->where('track_inventory', true)
                    ->whereColumn('inventories.quantity', '<=', 'items.reorder_level');
            });
        }

        // Filter by category
        if ($categoryId = $request->input('category_id')) {
            $query->whereHas('item', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'quantity');
        $sortOrder = $request->input('sort_order', 'asc');
        
        if (in_array($sortBy, ['quantity', 'reserved_quantity', 'unit_cost'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $inventory = $query->paginate($this->validatedPerPage($request));

        return $this->paginated($inventory->through(fn ($item) => new InventoryResource($item)));
    }

    /**
     * Get specific inventory item
     */
    public function show(Inventory $inventory): JsonResponse
    {
        $inventory->load(['item.category']);
        return $this->success(new InventoryResource($inventory));
    }

    /**
     * Adjust inventory quantity
     */
    public function adjust(Request $request, Inventory $inventory): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric',
            'type' => 'required|in:add,subtract,set',
            'reason' => 'required|string|max:255',
            'reference' => 'nullable|string|max:100',
        ]);

        $previousQuantity = $inventory->quantity;

        switch ($validated['type']) {
            case 'add':
                $newQuantity = $previousQuantity + $validated['quantity'];
                break;
            case 'subtract':
                $newQuantity = $previousQuantity - $validated['quantity'];
                if ($newQuantity < 0) {
                    return $this->validationError([
                        'quantity' => ['Insufficient inventory quantity'],
                    ]);
                }
                break;
            case 'set':
                $newQuantity = $validated['quantity'];
                break;
        }

        $inventory->update(['quantity' => $newQuantity]);

        // Create history record
        InventoryHistory::create([
            'tenant_id' => $this->getTenantId(),
            'inventory_id' => $inventory->id,
            'item_id' => $inventory->item_id,
            'type' => 'adjustment',
            'quantity_change' => $newQuantity - $previousQuantity,
            'quantity_before' => $previousQuantity,
            'quantity_after' => $newQuantity,
            'reference' => $validated['reference'],
            'notes' => $validated['reason'],
            'created_by' => auth()->id(),
        ]);

        $inventory->load(['item']);
        return $this->success(new InventoryResource($inventory), 'Inventory adjusted successfully');
    }

    /**
     * Get inventory history
     */
    public function history(Request $request, Inventory $inventory): JsonResponse
    {
        $query = InventoryHistory::where('inventory_id', $inventory->id)
            ->with('createdByUser');

        // Filter by type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Filter by date range
        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $query->orderBy('created_at', 'desc');

        $history = $query->paginate($this->validatedPerPage($request));

        return $this->paginated($history);
    }

    /**
     * Get inventory summary
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $totalItems = Inventory::where('tenant_id', $tenantId)->count();
        $totalQuantity = Inventory::where('tenant_id', $tenantId)->sum('quantity');
        $totalValue = Inventory::where('tenant_id', $tenantId)
            ->selectRaw('SUM(quantity * unit_cost) as total')
            ->value('total') ?? 0;

        $lowStockCount = Inventory::where('tenant_id', $tenantId)
            ->whereHas('item', function ($q) {
                $q->where('track_inventory', true)
                    ->whereColumn('inventories.quantity', '<=', 'items.reorder_level');
            })
            ->count();

        $outOfStockCount = Inventory::where('tenant_id', $tenantId)
            ->where('quantity', '<=', 0)
            ->whereHas('item', function ($q) {
                $q->where('track_inventory', true);
            })
            ->count();

        return $this->success([
            'total_items' => $totalItems,
            'total_quantity' => (float) $totalQuantity,
            'total_value' => (float) $totalValue,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
        ]);
    }
}
