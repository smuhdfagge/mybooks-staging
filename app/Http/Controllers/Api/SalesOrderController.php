<?php

namespace App\Http\Controllers\Api;

use App\Actions\SalesOrders\DeleteSalesOrder;
use App\Actions\SalesOrders\SaveSalesOrder;
use App\Http\Requests\StoreSalesOrderRequest;
use App\Http\Requests\UpdateSalesOrderRequest;
use App\Http\Resources\SalesOrderResource;
use App\Models\SalesOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * Create a sales order
     */
    public function store(StoreSalesOrderRequest $request, SaveSalesOrder $save): JsonResponse
    {
        // Same rules and code as the web form (R3, Q5), in a transaction (R6).
        $order = $save->create($this->getTenantId(), $request->validated(), auth()->id());
        $order->load(['customer', 'items.item']);

        return $this->created(new SalesOrderResource($order), 'Sales order created successfully');
    }

    /**
     * Update a sales order
     */
    public function update(UpdateSalesOrderRequest $request, SalesOrder $salesOrder, SaveSalesOrder $save): JsonResponse
    {
        $salesOrder = $save->update($salesOrder, $request->validated());
        $salesOrder->load(['customer', 'items.item']);

        return $this->success(new SalesOrderResource($salesOrder), 'Sales order updated successfully');
    }

    /**
     * Delete a sales order
     */
    public function destroy(SalesOrder $salesOrder, DeleteSalesOrder $delete): JsonResponse
    {
        if ($reason = $delete->blockedBecause($salesOrder)) {
            return $this->error($reason, 422);
        }

        $delete->handle($salesOrder);

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
