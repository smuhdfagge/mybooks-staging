<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use App\Http\Resources\CustomerResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerController extends BaseApiController
{
    /**
     * Get all customers
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Sorting
        [$sortBy, $sortOrder] = $this->validateSortParameters(
            $request,
            ['name', 'email', 'phone', 'company_name', 'is_active', 'created_at', 'updated_at'],
            'name'
        );
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $customers = $query->paginate($this->validatedPerPage($request));

        return $this->paginated($customers->through(fn ($customer) => new CustomerResource($customer)));
    }

    /**
     * Get a specific customer
     */
    public function show(Customer $customer): JsonResponse
    {
        return $this->success(new CustomerResource($customer));
    }

    /**
     * Create a new customer
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:100',
            'billing_address' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'credit_limit' => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $customer = Customer::create($validated);

        return $this->created(new CustomerResource($customer), 'Customer created successfully');
    }

    /**
     * Update a customer
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:100',
            'billing_address' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'credit_limit' => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $customer->update($validated);

        return $this->success(new CustomerResource($customer), 'Customer updated successfully');
    }

    /**
     * Delete a customer
     */
    public function destroy(Customer $customer): JsonResponse
    {
        // Check if customer has related records
        if ($customer->invoices()->exists()) {
            return $this->error('Cannot delete customer with existing invoices', 422);
        }

        $customer->delete();

        return $this->success(null, 'Customer deleted successfully');
    }

    /**
     * Get customer statistics
     */
    public function statistics(Customer $customer): JsonResponse
    {
        $totalInvoices = $customer->invoices()->count();
        $totalSales = $customer->invoices()->sum('total');
        $outstandingBalance = $customer->invoices()
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->sum('balance_due');
        $totalPayments = $customer->payments()->sum('amount');

        return $this->success([
            'total_invoices' => $totalInvoices,
            'total_sales' => (float) $totalSales,
            'outstanding_balance' => (float) $outstandingBalance,
            'total_payments' => (float) $totalPayments,
            'deposit_balance' => (float) $customer->deposit_balance,
        ]);
    }
}
