<?php

namespace App\Http\Controllers\Api;

use App\Actions\Inventory\AdjustStock;
use App\Http\Resources\InventoryResource;
use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InventoryController extends BaseApiController
{
    /**
     * Get all inventory items
     */
    public function index(Request $request): JsonResponse
    {
        // One record per item per warehouse (session 12).
        $query = Inventory::with(['item.category', 'warehouse']);

        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', (int) $warehouseId);
        }

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
        $inventory->load(['item.category', 'warehouse']);

        return $this->success(new InventoryResource($inventory));
    }

    /**
     * Adjust inventory quantity. Posts the adjustment journal at cost like
     * the web form (F1): optional unit_cost (stock in), account_id (instead
     * of Stock Losses), date and warehouse via the record.
     */
    public function adjust(Request $request, Inventory $inventory, AdjustStock $adjust): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0',
            'type' => 'required|in:add,subtract,set',
            'reason' => 'required|string|max:255',
            'reference' => 'nullable|string|max:100',
            'unit_cost' => 'nullable|numeric|min:0',
            'account_id' => 'nullable|integer',
            'date' => 'nullable|date|before_or_equal:today',
        ]);

        $mode = match ($validated['type']) {
            'add' => AdjustStock::IN,
            'subtract' => AdjustStock::OUT,
            default => AdjustStock::SET,
        };

        try {
            $adjust->handle($inventory->item, $mode, (float) $validated['quantity'],
                warehouseId: (int) $inventory->warehouse_id,
                unitCost: isset($validated['unit_cost']) ? (float) $validated['unit_cost'] : null,
                reason: trim($validated['reason'].(! empty($validated['reference']) ? " (ref {$validated['reference']})" : '')),
                accountId: ! empty($validated['account_id']) ? (int) $validated['account_id'] : null,
                date: $validated['date'] ?? null,
                label: $mode === AdjustStock::SET ? 'Stock count' : 'Stock adjustment',
                referenceType: 'api_adjustment',
                historyType: 'adjustment');
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        $inventory = $inventory->fresh(['item']);

        return $this->success(new InventoryResource($inventory), 'Inventory adjusted successfully');
    }

    /**
     * Get inventory history
     */
    public function history(Request $request, Inventory $inventory): JsonResponse
    {
        // History of this item in this record's warehouse (session 12).
        $query = InventoryHistory::where('tenant_id', $inventory->tenant_id)
            ->where('item_id', $inventory->item_id)
            ->where('warehouse_id', $inventory->warehouse_id)
            ->with('createdBy');

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

        // Counted per item across its warehouses (session 12).
        $totalItems = Inventory::where('tenant_id', $tenantId)->distinct()->count('item_id');
        $totalQuantity = Inventory::where('tenant_id', $tenantId)->sum('quantity');
        $totalValue = Inventory::where('tenant_id', $tenantId)
            ->selectRaw('SUM(quantity * unit_cost) as total')
            ->value('total') ?? 0;

        $stocked = Item::where('tenant_id', $tenantId)->where('track_inventory', true)->whereHas('inventories');
        $lowStockCount = (clone $stocked)->whereRaw(Item::onHandSql().' <= items.reorder_level')->count();
        $outOfStockCount = (clone $stocked)->whereRaw(Item::onHandSql().' <= 0')->count();

        return $this->success([
            'total_items' => $totalItems,
            'total_quantity' => (float) $totalQuantity,
            'total_value' => (float) $totalValue,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
        ]);
    }

    /**
     * The business's warehouses (session 12), default first. Documents
     * take an optional warehouse_id; without one they use the default.
     */
    public function warehouses(): JsonResponse
    {
        $warehouses = Warehouse::orderByDesc('is_default')->orderBy('name')->get()
            ->map(fn (Warehouse $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'code' => $w->code,
                'address' => $w->address,
                'is_default' => (bool) $w->is_default,
                'is_active' => (bool) $w->is_active,
            ]);

        return $this->success($warehouses);
    }
}
