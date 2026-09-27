<?php

namespace App\Http\Controllers\Api;

use App\Models\TaxRate;
use App\Http\Resources\TaxRateResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TaxRateController extends BaseApiController
{
    /**
     * Get all tax rates
     */
    public function index(Request $request): JsonResponse
    {
        $query = TaxRate::query();

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by applies_to
        if ($appliesTo = $request->input('applies_to')) {
            $query->where(function ($q) use ($appliesTo) {
                $q->where('applies_to', $appliesTo)
                    ->orWhere('applies_to', 'both');
            });
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter default rates
        if ($request->boolean('is_default')) {
            $query->where('is_default', true);
        }

        // Sorting
        [$sortBy, $sortOrder] = $this->validateSortParameters(
            $request,
            ['name', 'rate', 'applies_to', 'is_active', 'is_default', 'created_at', 'updated_at'],
            'name'
        );
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $taxRates = $query->paginate($this->validatedPerPage($request, 50));

        return $this->paginated($taxRates->through(fn ($rate) => new TaxRateResource($rate)));
    }

    /**
     * Get a specific tax rate
     */
    public function show(TaxRate $taxRate): JsonResponse
    {
        return $this->success(new TaxRateResource($taxRate));
    }

    /**
     * Create a new tax rate
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'rate' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string',
            'applies_to' => 'required|in:sales,purchases,both',
            'is_compound' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['tenant_id'] = $this->getTenantId();

        // If this is set as default, unset other defaults for the same applies_to
        if ($validated['is_default'] ?? false) {
            TaxRate::where('tenant_id', $validated['tenant_id'])
                ->where('is_default', true)
                ->where(function ($q) use ($validated) {
                    $q->where('applies_to', $validated['applies_to'])
                        ->orWhere('applies_to', 'both');
                })
                ->update(['is_default' => false]);
        }

        $taxRate = TaxRate::create($validated);

        return $this->created(new TaxRateResource($taxRate), 'Tax rate created successfully');
    }

    /**
     * Update a tax rate
     */
    public function update(Request $request, TaxRate $taxRate): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'rate' => 'sometimes|numeric|min:0|max:100',
            'description' => 'nullable|string',
            'applies_to' => 'sometimes|in:sales,purchases,both',
            'is_compound' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // If this is set as default, unset other defaults
        if (($validated['is_default'] ?? false) && !$taxRate->is_default) {
            $appliesTo = $validated['applies_to'] ?? $taxRate->applies_to;
            TaxRate::where('tenant_id', $taxRate->tenant_id)
                ->where('id', '!=', $taxRate->id)
                ->where('is_default', true)
                ->where(function ($q) use ($appliesTo) {
                    $q->where('applies_to', $appliesTo)
                        ->orWhere('applies_to', 'both');
                })
                ->update(['is_default' => false]);
        }

        $taxRate->update($validated);

        return $this->success(new TaxRateResource($taxRate), 'Tax rate updated successfully');
    }

    /**
     * Delete a tax rate
     */
    public function destroy(TaxRate $taxRate): JsonResponse
    {
        $taxRate->delete();

        return $this->success(null, 'Tax rate deleted successfully');
    }
}
