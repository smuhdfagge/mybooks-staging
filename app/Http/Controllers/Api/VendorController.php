<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreVendorRequest;
use App\Http\Requests\UpdateVendorRequest;
use App\Http\Resources\VendorResource;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    public function store(StoreVendorRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $vendor = Vendor::create($validated);

        return $this->created(new VendorResource($vendor), 'Vendor created successfully');
    }

    /**
     * Update a vendor
     */
    public function update(UpdateVendorRequest $request, Vendor $vendor): JsonResponse
    {
        $validated = $request->validated();

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
