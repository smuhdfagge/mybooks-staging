<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\SalesOrderResource;
use App\Models\SalesOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesOrderController extends BaseApiController
{
    /**
     * Get all sales orders
     */
    public function index(Request $request): JsonResponse
    {
        $query = SalesOrder::with(['customer', 'items.item']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by customer
        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        // Filter by date range
        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('order_date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('order_date', '<=', $toDate);
        }

        // Sorting
        [$sortBy, $sortOrder] = $this->validateSortParameters(
            $request,
            ['order_number', 'order_date', 'status', 'total', 'created_at', 'updated_at'],
            'created_at'
        );
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $orders = $query->paginate($this->validatedPerPage($request));

        return $this->paginated($orders->through(fn ($order) => new SalesOrderResource($order)));
    }

    /**
     * Get a specific sales order
     */
    public function show(SalesOrder $salesOrder): JsonResponse
    {
        $salesOrder->load(['customer', 'items.item', 'invoices', 'createdBy']);

        return $this->success(new SalesOrderResource($salesOrder));
    }

    /**
     * Create a new sales order
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'reference' => 'nullable|string|max:100',
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'status' => 'sometimes|in:draft,confirmed,processing,completed,cancelled',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:fixed,percentage',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $validated['order_number'] = SalesOrder::generateNumber($tenantId);
        $validated['tenant_id'] = $tenantId;
        $validated['created_by'] = auth()->id();

        // Calculate totals: VAT after the discount (A4).
        $totals = \App\Services\Sales\DocumentTotals::calculate(
            $validated['items'], $validated['discount_type'] ?? null, $validated['discount_amount'] ?? 0
        );
        $validated['items'] = $totals['lines'];
        $validated['subtotal'] = $totals['subtotal'];
        $validated['tax_amount'] = $totals['tax_amount'];
        $validated['discount_amount'] = $totals['discount_amount'];
        $validated['total'] = $totals['total'];

        $order = SalesOrder::create(collect($validated)->except('items')->toArray());

        foreach ($validated['items'] as $item) {
            $order->items()->create($item);
        }

        $order->load(['customer', 'items.item']);

        return $this->created(new SalesOrderResource($order), 'Sales order created successfully');
    }

    /**
     * Update a sales order
     */
    public function update(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        if (in_array($salesOrder->status, ['completed', 'cancelled'])) {
            return $this->forbidden('Cannot modify a completed or cancelled order');
        }

        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'customer_id' => ['sometimes', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'reference' => 'nullable|string|max:100',
            'order_date' => 'sometimes|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'status' => 'sometimes|in:draft,confirmed,processing,completed,cancelled',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'sometimes|array|min:1',
            'items.*.id' => ['nullable', Rule::exists('sales_order_items', 'id')->where('sales_order_id', $salesOrder->id)],
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:fixed,percentage',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        if (isset($validated['items'])) {
            // VAT after the discount (A4). The stored discount is money, so
            // without a new one keep it as a fixed amount.
            $totals = \App\Services\Sales\DocumentTotals::calculate(
                $validated['items'],
                array_key_exists('discount_amount', $validated) ? ($validated['discount_type'] ?? null) : 'fixed',
                array_key_exists('discount_amount', $validated) ? $validated['discount_amount'] : $salesOrder->discount_amount,
            );
            $validated['items'] = $totals['lines'];
            $validated['subtotal'] = $totals['subtotal'];
            $validated['tax_amount'] = $totals['tax_amount'];
            $validated['discount_amount'] = $totals['discount_amount'];
            $validated['total'] = $totals['total'];

            $salesOrder->items()->delete();
            foreach ($validated['items'] as $item) {
                $salesOrder->items()->create($item);
            }
        }

        $salesOrder->update(collect($validated)->except('items')->toArray());
        $salesOrder->load(['customer', 'items.item']);

        return $this->success(new SalesOrderResource($salesOrder), 'Sales order updated successfully');
    }

    /**
     * Delete a sales order
     */
    public function destroy(SalesOrder $salesOrder): JsonResponse
    {
        if ($salesOrder->invoices()->exists()) {
            return $this->error('Cannot delete sales order with invoices', 422);
        }

        $salesOrder->items()->delete();
        $salesOrder->delete();

        return $this->success(null, 'Sales order deleted successfully');
    }

    /**
     * Get sales order summary
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $totalOrders = SalesOrder::where('tenant_id', $tenantId)->count();
        $totalAmount = SalesOrder::where('tenant_id', $tenantId)->sum('total');

        return $this->success([
            'total_orders' => $totalOrders,
            'total_amount' => (float) $totalAmount,
            'by_status' => [
                'draft' => SalesOrder::where('tenant_id', $tenantId)->where('status', 'draft')->count(),
                'confirmed' => SalesOrder::where('tenant_id', $tenantId)->where('status', 'confirmed')->count(),
                'processing' => SalesOrder::where('tenant_id', $tenantId)->where('status', 'processing')->count(),
                'completed' => SalesOrder::where('tenant_id', $tenantId)->where('status', 'completed')->count(),
                'cancelled' => SalesOrder::where('tenant_id', $tenantId)->where('status', 'cancelled')->count(),
            ],
        ]);
    }
}
