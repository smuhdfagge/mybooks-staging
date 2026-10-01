<?php

namespace App\Http\Controllers\Api;

use App\Actions\Bills\DeleteBill;
use App\Actions\Bills\SaveBill;
use App\Http\Requests\StoreBillRequest;
use App\Http\Requests\UpdateBillRequest;
use App\Http\Resources\BillResource;
use App\Models\Bill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillController extends BaseApiController
{
    /**
     * Get all bills
     */
    public function index(Request $request): JsonResponse
    {
        $query = Bill::with(['vendor', 'items.item']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('bill_number', 'like', "%{$search}%")
                    ->orWhere('vendor_bill_number', 'like', "%{$search}%")
                    ->orWhereHas('vendor', function ($vq) use ($search) {
                        $vq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by vendor
        if ($vendorId = $request->input('vendor_id')) {
            $query->where('vendor_id', $vendorId);
        }

        // Filter by date range
        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('bill_date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('bill_date', '<=', $toDate);
        }

        // Filter overdue bills
        if ($request->boolean('overdue')) {
            $query->where('status', '!=', 'paid')
                ->whereDate('due_date', '<', now());
        }

        // Sorting
        [$sortBy, $sortOrder] = $this->validateSortParameters(
            $request,
            ['bill_number', 'vendor_bill_number', 'bill_date', 'due_date', 'status', 'total', 'balance_due', 'created_at', 'updated_at'],
            'created_at'
        );
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $bills = $query->paginate($this->validatedPerPage($request));

        return $this->paginated($bills->through(fn ($bill) => new BillResource($bill)));
    }

    /**
     * Get a specific bill
     */
    public function show(Bill $bill): JsonResponse
    {
        $bill->load(['vendor', 'items.item', 'payments', 'createdBy']);

        return $this->success(new BillResource($bill));
    }

    /**
     * Create a new bill
     */
    public function store(StoreBillRequest $request, SaveBill $save): JsonResponse
    {
        // Same rules and code as the web form (R3, Q5), in a transaction (R6).
        $bill = $save->create($this->getTenantId(), $request->validated(), auth()->id());
        $bill->load(['vendor', 'items.item']);

        return $this->created(new BillResource($bill), 'Bill created successfully');
    }

    /**
     * Update a bill
     */
    public function update(UpdateBillRequest $request, Bill $bill, SaveBill $save): JsonResponse
    {
        $bill = $save->update($bill, $request->validated());
        $bill->load(['vendor', 'items.item']);

        return $this->success(new BillResource($bill), 'Bill updated successfully');
    }

    /**
     * Delete a bill
     */
    public function destroy(Bill $bill, DeleteBill $delete): JsonResponse
    {
        if ($reason = $delete->blockedBecause($bill)) {
            return $this->error($reason, 422);
        }
        $delete->handle($bill);

        return $this->success(null, 'Bill deleted successfully');
    }

    /**
     * Get bill summary/statistics
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $totalBills = Bill::where('tenant_id', $tenantId)->count();
        $totalAmount = Bill::where('tenant_id', $tenantId)->sum('total');
        $totalPaid = Bill::where('tenant_id', $tenantId)->sum('amount_paid');
        $totalOutstanding = Bill::where('tenant_id', $tenantId)
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->sum('balance_due');
        $overdueCount = Bill::where('tenant_id', $tenantId)
            ->where('status', '!=', 'paid')
            ->whereDate('due_date', '<', now())
            ->count();

        return $this->success([
            'total_bills' => $totalBills,
            'total_amount' => (float) $totalAmount,
            'total_paid' => (float) $totalPaid,
            'total_outstanding' => (float) $totalOutstanding,
            'overdue_count' => $overdueCount,
            'by_status' => [
                'draft' => Bill::where('tenant_id', $tenantId)->where('status', 'draft')->count(),
                'unpaid' => Bill::where('tenant_id', $tenantId)->where('status', 'unpaid')->count(),
                'partial' => Bill::where('tenant_id', $tenantId)->where('status', 'partial')->count(),
                'paid' => Bill::where('tenant_id', $tenantId)->where('status', 'paid')->count(),
                'overdue' => Bill::where('tenant_id', $tenantId)->where('status', 'overdue')->count(),
            ],
        ]);
    }
}
