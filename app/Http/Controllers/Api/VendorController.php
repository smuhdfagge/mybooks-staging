<?php

namespace App\Http\Controllers\Api;

use App\Models\Vendor;
use App\Http\Resources\VendorResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VendorController extends BaseApiController
{
    /**
     * Get all vendors
     */
    public function index(Request $request): JsonResponse
    {
        $query = Vendor::query();

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
        $vendors = $query->paginate($this->validatedPerPage($request));

        return $this->paginated($vendors->through(fn ($vendor) => new VendorResource($vendor)));
    }

    /**
     * Get a specific vendor
     */
    public function show(Vendor $vendor): JsonResponse
    {
        return $this->success(new VendorResource($vendor));
    }

    /**
     * Create a new vendor
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'payment_terms' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $vendor = Vendor::create($validated);

        return $this->created(new VendorResource($vendor), 'Vendor created successfully');
    }

    /**
     * Update a vendor
     */
    public function update(Request $request, Vendor $vendor): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'payment_terms' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $vendor->update($validated);

        return $this->success(new VendorResource($vendor), 'Vendor updated successfully');
    }

    /**
     * Delete a vendor
     */
    public function destroy(Vendor $vendor): JsonResponse
    {
        // Check if vendor has related records
        if ($vendor->bills()->exists()) {
            return $this->error('Cannot delete vendor with existing bills', 422);
        }

        $vendor->delete();

        return $this->success(null, 'Vendor deleted successfully');
    }

    /**
     * Get vendor statistics
     */
    public function statistics(Vendor $vendor): JsonResponse
    {
        $totalBills = $vendor->bills()->count();
        $totalPurchases = $vendor->bills()->sum('total');
        $outstandingBalance = $vendor->bills()
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->sum('balance_due');
        $totalPayments = $vendor->payments()->sum('amount');

        return $this->success([
            'total_bills' => $totalBills,
            'total_purchases' => (float) $totalPurchases,
            'outstanding_balance' => (float) $outstandingBalance,
            'total_payments' => (float) $totalPayments,
        ]);
    }
}
