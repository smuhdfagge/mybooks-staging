<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends BaseApiController
{
    /**
     * Get all expenses
     */
    public function index(Request $request): JsonResponse
    {
        $query = Expense::with(['vendor', 'expenseAccount', 'bank', 'createdBy']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('expense_number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
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

        // Filter by expense account
        if ($accountId = $request->input('expense_account_id')) {
            $query->where('expense_account_id', $accountId);
        }

        // Filter by date range
        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('expense_date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('expense_date', '<=', $toDate);
        }

        // Sorting
        [$sortBy, $sortOrder] = $this->validateSortParameters(
            $request,
            ['expense_number', 'name', 'expense_date', 'status', 'total', 'created_at', 'updated_at'],
            'created_at'
        );
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $expenses = $query->paginate($this->validatedPerPage($request));

        return $this->paginated($expenses->through(fn ($expense) => new ExpenseResource($expense)));
    }

    /**
     * Get a specific expense
     */
    public function show(Expense $expense): JsonResponse
    {
        $expense->load(['vendor', 'expenseAccount', 'bank', 'customer', 'createdBy', 'approvedByUser']);

        return $this->success(new ExpenseResource($expense));
    }

    /**
     * Create a new expense
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'vendor_id' => ['nullable', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'expense_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'paid_through_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('tenant_id', $tenantId)],
            'name' => 'required|string|max:255',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_billable' => 'boolean',
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'notes' => 'nullable|string',
            'status' => 'sometimes|in:draft,pending_approval,approved,rejected,paid',
        ]);

        $validated['expense_number'] = Expense::generateNumber($tenantId);
        $validated['tenant_id'] = $tenantId;
        $validated['created_by'] = auth()->id();
        $validated['total'] = ($validated['amount'] ?? 0) + ($validated['tax_amount'] ?? 0);
        $validated['status'] = $validated['status'] ?? Expense::STATUS_DRAFT;

        $expense = Expense::create($validated);
        $expense->load(['vendor', 'expenseAccount', 'bank']);

        return $this->created(new ExpenseResource($expense), 'Expense created successfully');
    }

    /**
     * Update an expense
     */
    public function update(Request $request, Expense $expense): JsonResponse
    {
        // Cannot update approved/paid expenses
        if (in_array($expense->status, [Expense::STATUS_APPROVED, Expense::STATUS_PAID])) {
            return $this->forbidden('Cannot modify an approved or paid expense');
        }

        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'vendor_id' => ['nullable', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'expense_account_id' => ['sometimes', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'paid_through_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('tenant_id', $tenantId)],
            'name' => 'sometimes|string|max:255',
            'expense_date' => 'sometimes|date',
            'amount' => 'sometimes|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_billable' => 'boolean',
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'notes' => 'nullable|string',
        ]);

        if (isset($validated['amount']) || isset($validated['tax_amount'])) {
            $amount = $validated['amount'] ?? $expense->amount;
            $taxAmount = $validated['tax_amount'] ?? $expense->tax_amount;
            $validated['total'] = $amount + $taxAmount;
        }

        $expense->update($validated);
        $expense->load(['vendor', 'expenseAccount', 'bank']);

        return $this->success(new ExpenseResource($expense), 'Expense updated successfully');
    }

    /**
     * Delete an expense
     */
    public function destroy(Expense $expense): JsonResponse
    {
        if ($expense->status === Expense::STATUS_PAID) {
            return $this->error('Cannot delete a paid expense', 422);
        }

        $expense->delete();

        return $this->success(null, 'Expense deleted successfully');
    }

    /**
     * Submit expense for approval
     */
    public function submit(Expense $expense): JsonResponse
    {
        if ($expense->status !== Expense::STATUS_DRAFT) {
            return $this->error('Only draft expenses can be submitted', 422);
        }

        $expense->update([
            'status' => Expense::STATUS_PENDING_APPROVAL,
            'submitted_at' => now(),
        ]);

        return $this->success(new ExpenseResource($expense), 'Expense submitted for approval');
    }

    /**
     * Approve expense
     */
    public function approve(Expense $expense): JsonResponse
    {
        if ($expense->status !== Expense::STATUS_PENDING_APPROVAL) {
            return $this->error('Only pending expenses can be approved', 422);
        }

        $expense->update([
            'status' => Expense::STATUS_APPROVED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $this->success(new ExpenseResource($expense), 'Expense approved');
    }

    /**
     * Reject expense
     */
    public function reject(Request $request, Expense $expense): JsonResponse
    {
        if ($expense->status !== Expense::STATUS_PENDING_APPROVAL) {
            return $this->error('Only pending expenses can be rejected', 422);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $expense->update([
            'status' => Expense::STATUS_REJECTED,
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return $this->success(new ExpenseResource($expense), 'Expense rejected');
    }

    /**
     * Get expense summary/statistics
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $totalExpenses = Expense::where('tenant_id', $tenantId)->count();
        $totalAmount = Expense::where('tenant_id', $tenantId)->sum('total');
        $paidAmount = Expense::where('tenant_id', $tenantId)
            ->where('status', Expense::STATUS_PAID)
            ->sum('total');
        $pendingApproval = Expense::where('tenant_id', $tenantId)
            ->where('status', Expense::STATUS_PENDING_APPROVAL)
            ->count();

        return $this->success([
            'total_expenses' => $totalExpenses,
            'total_amount' => (float) $totalAmount,
            'paid_amount' => (float) $paidAmount,
            'pending_approval' => $pendingApproval,
            'by_status' => [
                'draft' => Expense::where('tenant_id', $tenantId)->where('status', Expense::STATUS_DRAFT)->count(),
                'pending_approval' => Expense::where('tenant_id', $tenantId)->where('status', Expense::STATUS_PENDING_APPROVAL)->count(),
                'approved' => Expense::where('tenant_id', $tenantId)->where('status', Expense::STATUS_APPROVED)->count(),
                'rejected' => Expense::where('tenant_id', $tenantId)->where('status', Expense::STATUS_REJECTED)->count(),
                'paid' => Expense::where('tenant_id', $tenantId)->where('status', Expense::STATUS_PAID)->count(),
            ],
        ]);
    }
}
