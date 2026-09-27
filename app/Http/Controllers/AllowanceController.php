<?php

namespace App\Http\Controllers;

use App\Models\Allowance;
use Illuminate\Http\Request;

class AllowanceController extends Controller
{
    public function index()
    {
        return view('payroll.allowances.index');
    }

    public function create()
    {
        return view('payroll.allowances.create');
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

        Allowance::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $validated['name'],
            'amount_type' => $validated['amount_type'],
            'amount' => $validated['amount'],
            'is_taxable' => $validated['is_taxable'] ?? true,
            'is_active' => true,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('allowances.index')->with('success', 'Allowance created successfully.');
    }

    public function show(Allowance $allowance)
    {
        abort_unless($allowance->tenant_id === auth()->user()->tenant_id, 403);

        return view('payroll.allowances.show', compact('allowance'));
    }

    public function edit(Allowance $allowance)
    {
        abort_unless($allowance->tenant_id === auth()->user()->tenant_id, 403);

        return view('payroll.allowances.edit', compact('allowance'));
    }

    public function update(Request $request, Allowance $allowance)
    {
        abort_unless($allowance->tenant_id === auth()->user()->tenant_id, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount_type' => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0',
            'is_taxable' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:1000',
        ]);

        $allowance->update([
            'name' => $validated['name'],
            'amount_type' => $validated['amount_type'],
            'amount' => $validated['amount'],
            'is_taxable' => $validated['is_taxable'] ?? true,
            'is_active' => $validated['is_active'] ?? $allowance->is_active,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('allowances.index')->with('success', 'Allowance updated successfully.');
    }

    public function destroy(Allowance $allowance)
    {
        abort_unless($allowance->tenant_id === auth()->user()->tenant_id, 403);

        $allowance->delete();

        return redirect()->route('allowances.index')->with('success', 'Allowance deleted successfully.');
    }
}
