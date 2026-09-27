<?php

namespace App\Http\Controllers\Api;

use App\Models\Bill;
use App\Http\Resources\BillResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

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
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'vendor_id' => ['required', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'vendor_bill_number' => 'nullable|string|max:100',
            'bill_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:bill_date',
            'status' => 'sometimes|in:draft,unpaid,partial,paid,overdue',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        // Generate bill number
        $validated['bill_number'] = Bill::generateNumber($tenantId);
        $validated['tenant_id'] = $tenantId;
        $validated['created_by'] = auth()->id();

        // Calculate totals
        $subtotal = 0;
        $taxAmount = 0;

        foreach ($validated['items'] as &$item) {
            $itemSubtotal = $item['quantity'] * $item['unit_price'];

            if (!empty($item['discount'])) {
                $itemSubtotal -= $item['discount'];
            }

            $itemTax = 0;
            if (!empty($item['tax_rate'])) {
                $itemTax = $itemSubtotal * ($item['tax_rate'] / 100);
            }

            $item['tax_amount'] = $itemTax;
            $item['total'] = $itemSubtotal + $itemTax;

            $subtotal += $itemSubtotal;
            $taxAmount += $itemTax;
        }

        $discountAmount = $validated['discount_amount'] ?? 0;

        $validated['subtotal'] = $subtotal;
        $validated['tax_amount'] = $taxAmount;
        $validated['discount_amount'] = $discountAmount;
        $validated['total'] = $subtotal + $taxAmount - $discountAmount;
        $validated['balance_due'] = $validated['total'];
        $validated['amount_paid'] = 0;

        // Create bill
        $bill = Bill::create(collect($validated)->except('items')->toArray());

        // Create bill items
        foreach ($validated['items'] as $item) {
            $bill->items()->create($item);
        }

        $bill->load(['vendor', 'items.item']);

        return $this->created(new BillResource($bill), 'Bill created successfully');
    }

    /**
     * Update a bill
     */
    public function update(Request $request, Bill $bill): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'vendor_id' => ['sometimes', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'vendor_bill_number' => 'nullable|string|max:100',
            'bill_date' => 'sometimes|date',
            'due_date' => 'sometimes|date|after_or_equal:bill_date',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'sometimes|array|min:1',
            'items.*.id' => ['nullable', Rule::exists('bill_items', 'id')->where('bill_id', $bill->id)],
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        if (isset($validated['items'])) {
            $subtotal = 0;
            $taxAmount = 0;

            foreach ($validated['items'] as &$item) {
                $itemSubtotal = $item['quantity'] * $item['unit_price'];

                if (!empty($item['discount'])) {
                    $itemSubtotal -= $item['discount'];
                }

                $itemTax = 0;
                if (!empty($item['tax_rate'])) {
                    $itemTax = $itemSubtotal * ($item['tax_rate'] / 100);
                }

                $item['tax_amount'] = $itemTax;
                $item['total'] = $itemSubtotal + $itemTax;

                $subtotal += $itemSubtotal;
                $taxAmount += $itemTax;
            }

            $discountAmount = $validated['discount_amount'] ?? 0;

            $validated['subtotal'] = $subtotal;
            $validated['tax_amount'] = $taxAmount;
            $validated['discount_amount'] = $discountAmount;
            $validated['total'] = $subtotal + $taxAmount - $discountAmount;
            $validated['balance_due'] = $validated['total'] - $bill->amount_paid;

            $bill->items()->delete();
            foreach ($validated['items'] as $item) {
                $bill->items()->create($item);
            }
        }

        $bill->update(collect($validated)->except('items')->toArray());
        $bill->load(['vendor', 'items.item']);

        return $this->success(new BillResource($bill), 'Bill updated successfully');
    }

    /**
     * Delete a bill
     */
    public function destroy(Bill $bill): JsonResponse
    {
        if ($bill->payments()->exists()) {
            return $this->error('Cannot delete bill with payments', 422);
        }

        $bill->items()->delete();
        $bill->delete();

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
