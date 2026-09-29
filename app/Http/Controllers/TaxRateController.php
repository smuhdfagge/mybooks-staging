<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaxRateRequest;
use App\Http\Requests\UpdateTaxRateRequest;
use App\Models\TaxRate;

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

    public function store(StoreTaxRateRequest $request)
    {
        $validated = $request->validated();

        $tenantId = auth()->user()->tenant_id;

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

    public function update(UpdateTaxRateRequest $request, TaxRate $taxRate)
    {
        // A code already used in the business is refused by the request.
        $validated = $request->validated();

        $taxRate->update([
            'name' => $validated['name'] ?? $taxRate->name,
            'code' => $validated['code'] ?? null,
            'rate' => $validated['rate'] ?? $taxRate->rate,
            'type' => $validated['type'] ?? $taxRate->type,
            'applies_to' => $validated['applies_to'] ?? $taxRate->applies_to,
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
