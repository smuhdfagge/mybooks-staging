<?php

namespace App\Http\Controllers;

use App\Models\TaxGroup;
use App\Models\TaxRate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaxGroupController extends Controller
{
    public function index()
    {
        return view('tax-groups.index');
    }

    public function create()
    {
        $taxRates = TaxRate::where('is_active', true)->orderBy('name')->get();

        return view('tax-groups.create', compact('taxRates'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'tax_rates' => 'required|array|min:1',
            'tax_rates.*' => Rule::exists('tax_rates', 'id')->where('tenant_id', $tenantId),
        ]);

        // Check for unique code within tenant
        if (! empty($validated['code'])) {
            $exists = TaxGroup::where('tenant_id', $tenantId)
                ->where('code', $validated['code'])
                ->exists();
            if ($exists) {
                return redirect()->back()->withInput()->with('error', 'A tax group with this code already exists.');
            }
        }

        $taxGroup = TaxGroup::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Attach tax rates with sort order
        $syncData = [];
        foreach ($validated['tax_rates'] as $index => $taxRateId) {
            $syncData[$taxRateId] = ['sort_order' => $index];
        }
        $taxGroup->taxRates()->sync($syncData);

        return redirect()->route('tax-groups.index')->with('success', 'Tax group created successfully.');
    }

    public function show(TaxGroup $taxGroup)
    {
        $taxGroup->load('taxRates');

        return view('tax-groups.show', compact('taxGroup'));
    }

    public function edit(TaxGroup $taxGroup)
    {
        $taxRates = TaxRate::where('is_active', true)->orderBy('name')->get();
        $taxGroup->load('taxRates');

        return view('tax-groups.edit', compact('taxGroup', 'taxRates'));
    }

    public function update(Request $request, TaxGroup $taxGroup)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'tax_rates' => 'required|array|min:1',
            'tax_rates.*' => Rule::exists('tax_rates', 'id')->where('tenant_id', $tenantId),
        ]);

        // Check for unique code within tenant (excluding current)
        if (! empty($validated['code'])) {
            $exists = TaxGroup::where('tenant_id', $tenantId)
                ->where('code', $validated['code'])
                ->where('id', '!=', $taxGroup->id)
                ->exists();
            if ($exists) {
                return redirect()->back()->withInput()->with('error', 'A tax group with this code already exists.');
            }
        }

        $taxGroup->update([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Sync tax rates with sort order
        $syncData = [];
        foreach ($validated['tax_rates'] as $index => $taxRateId) {
            $syncData[$taxRateId] = ['sort_order' => $index];
        }
        $taxGroup->taxRates()->sync($syncData);

        return redirect()->route('tax-groups.index')->with('success', 'Tax group updated successfully.');
    }

    public function destroy(TaxGroup $taxGroup)
    {
        // Check if tax group is in use
        if ($taxGroup->items()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete tax group that is assigned to items.');
        }

        $taxGroup->taxRates()->detach();
        $taxGroup->delete();

        return redirect()->route('tax-groups.index')->with('success', 'Tax group deleted successfully.');
    }
}
