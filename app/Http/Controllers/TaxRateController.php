<?php

namespace App\Http\Controllers;

use App\Models\TaxRate;
use Illuminate\Http\Request;

class TaxRateController extends Controller
{
    public function index()
    {
        return view('tax-rates.index');
    }

    public function create()
    {
        return view('tax-rates.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20',
            'rate' => 'required|numeric|min:0|max:100',
            'type' => 'required|in:inclusive,exclusive',
            'applies_to' => 'required|in:sales,purchases,both',
            'tax_number' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_compound' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $tenantId = auth()->user()->tenant_id;

        // Check for unique code within tenant
        if (! empty($validated['code'])) {
            $exists = TaxRate::where('tenant_id', $tenantId)
                ->where('code', $validated['code'])
                ->exists();
            if ($exists) {
                return redirect()->back()->withInput()->with('error', 'A tax rate with this code already exists.');
            }
        }

        $taxRate = TaxRate::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'rate' => $validated['rate'],
            'type' => $validated['type'],
            'applies_to' => $validated['applies_to'],
            'tax_number' => $validated['tax_number'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_compound' => $validated['is_compound'] ?? false,
            'is_default' => $validated['is_default'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if ($taxRate->is_default) {
            $taxRate->setAsDefault();
        }

        return redirect()->route('tax-rates.index')->with('success', 'Tax rate created successfully.');
    }

    public function show(TaxRate $taxRate)
    {
        return view('tax-rates.show', compact('taxRate'));
    }

    public function edit(TaxRate $taxRate)
    {
        return view('tax-rates.edit', compact('taxRate'));
    }

    public function update(Request $request, TaxRate $taxRate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20',
            'rate' => 'required|numeric|min:0|max:100',
            'type' => 'required|in:inclusive,exclusive',
            'applies_to' => 'required|in:sales,purchases,both',
            'tax_number' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_compound' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $tenantId = auth()->user()->tenant_id;

        // Check for unique code within tenant (excluding current)
        if (! empty($validated['code'])) {
            $exists = TaxRate::where('tenant_id', $tenantId)
                ->where('code', $validated['code'])
                ->where('id', '!=', $taxRate->id)
                ->exists();
            if ($exists) {
                return redirect()->back()->withInput()->with('error', 'A tax rate with this code already exists.');
            }
        }

        $taxRate->update([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'rate' => $validated['rate'],
            'type' => $validated['type'],
            'applies_to' => $validated['applies_to'],
            'tax_number' => $validated['tax_number'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_compound' => $validated['is_compound'] ?? false,
            'is_default' => $validated['is_default'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if ($taxRate->is_default) {
            $taxRate->setAsDefault();
        }

        return redirect()->route('tax-rates.index')->with('success', 'Tax rate updated successfully.');
    }

    public function destroy(TaxRate $taxRate)
    {
        // Check if tax rate is in use
        if ($taxRate->items()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete tax rate that is assigned to items.');
        }

        $taxRate->delete();

        return redirect()->route('tax-rates.index')->with('success', 'Tax rate deleted successfully.');
    }

    public function setDefault(TaxRate $taxRate)
    {
        $taxRate->setAsDefault();

        return redirect()->back()->with('success', 'Default tax rate updated.');
    }
}
