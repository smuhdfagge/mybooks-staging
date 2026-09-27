<?php

namespace App\Http\Controllers;

use App\Models\Deduction;
use Illuminate\Http\Request;

class DeductionController extends Controller
{
    public function index()
    {
        return view('payroll.deductions.index');
    }

    public function create()
    {
        return view('payroll.deductions.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount_type' => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0',
            'is_taxable' => 'nullable|boolean',
            'description' => 'nullable|string|max:1000',
        ]);

        Deduction::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $validated['name'],
            'amount_type' => $validated['amount_type'],
            'amount' => $validated['amount'],
            'is_taxable' => $validated['is_taxable'] ?? false,
            'is_active' => true,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('deductions.index')->with('success', 'Deduction created successfully.');
    }

    public function show(Deduction $deduction)
    {
        abort_unless($deduction->tenant_id === auth()->user()->tenant_id, 403);

        return view('payroll.deductions.show', compact('deduction'));
    }

    public function edit(Deduction $deduction)
    {
        abort_unless($deduction->tenant_id === auth()->user()->tenant_id, 403);

        return view('payroll.deductions.edit', compact('deduction'));
    }

    public function update(Request $request, Deduction $deduction)
    {
        abort_unless($deduction->tenant_id === auth()->user()->tenant_id, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount_type' => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0',
            'is_taxable' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:1000',
        ]);

        $deduction->update([
            'name' => $validated['name'],
            'amount_type' => $validated['amount_type'],
            'amount' => $validated['amount'],
            'is_taxable' => $validated['is_taxable'] ?? false,
            'is_active' => $validated['is_active'] ?? $deduction->is_active,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('deductions.index')->with('success', 'Deduction updated successfully.');
    }

    public function destroy(Deduction $deduction)
    {
        abort_unless($deduction->tenant_id === auth()->user()->tenant_id, 403);

        $deduction->delete();

        return redirect()->route('deductions.index')->with('success', 'Deduction deleted successfully.');
    }
}
