<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $customer = Customer::create($validated);

        return $this->created(new CustomerResource($customer), 'Customer created successfully');
    }

    /**
     * Update a customer
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $validated = $request->validated();

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
