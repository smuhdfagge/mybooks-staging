<?php

namespace App\Http\Controllers;

use App\Models\Allowance;
use App\Models\Deduction;
use App\Models\SalaryStructure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalaryStructureController extends Controller
{
    public function index()
    {
        return view('payroll.salary-structures.index');
    }

    public function create()
    {
        $allowanceTemplates = Allowance::active()->orderBy('name')->get();
        $deductionTemplates = Deduction::active()->orderBy('name')->get();

        return view('payroll.salary-structures.create', compact('allowanceTemplates', 'deductionTemplates'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'basic_salary' => 'required|numeric|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'notes' => 'nullable|string|max:1000',
            'allowances' => 'nullable|array',
            'allowances.*.name' => 'required_with:allowances|string|max:255',
            'allowances.*.amount_type' => 'required_with:allowances|in:fixed,percentage',
            'allowances.*.amount' => 'required_with:allowances|numeric|min:0',
            'allowances.*.is_taxable' => 'nullable|boolean',
            'deductions' => 'nullable|array',
            'deductions.*.name' => 'required_with:deductions|string|max:255',
            'deductions.*.amount_type' => 'required_with:deductions|in:fixed,percentage',
            'deductions.*.amount' => 'required_with:deductions|numeric|min:0',
            'deductions.*.is_taxable' => 'nullable|boolean',
        ]);

        DB::beginTransaction();

        try {
            $structure = SalaryStructure::create([
                'tenant_id' => $tenantId,
                'name' => $validated['name'],
                'basic_salary' => $validated['basic_salary'],
                'effective_from' => $validated['effective_from'],
                'effective_to' => $validated['effective_to'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);

            // Create allowance items
            if (! empty($validated['allowances'])) {
                foreach ($validated['allowances'] as $index => $allowance) {
                    if (! empty($allowance['name']) && isset($allowance['amount'])) {
                        $structure->items()->create([
                            'type' => 'allowance',
                            'name' => $allowance['name'],
                            'amount_type' => $allowance['amount_type'],
                            'amount' => $allowance['amount'],
                            'is_taxable' => $allowance['is_taxable'] ?? true,
                            'sort_order' => $index,
                        ]);
                    }
                }
            }

            // Create deduction items
            if (! empty($validated['deductions'])) {
                foreach ($validated['deductions'] as $index => $deduction) {
                    if (! empty($deduction['name']) && isset($deduction['amount'])) {
                        $structure->items()->create([
                            'type' => 'deduction',
                            'name' => $deduction['name'],
                            'amount_type' => $deduction['amount_type'],
                            'amount' => $deduction['amount'],
                            'is_taxable' => $deduction['is_taxable'] ?? false,
                            'sort_order' => $index,
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('salary-structures.show', $structure)
                ->with('success', 'Salary structure created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->withErrors(['error' => 'Failed to create salary structure.']);
        }
    }

    public function show(SalaryStructure $salaryStructure)
    {
        abort_unless($salaryStructure->tenant_id === auth()->user()->tenant_id, 403);

        $salaryStructure->load(['items', 'createdBy', 'versions.changedByUser']);

        return view('payroll.salary-structures.show', compact('salaryStructure'));
    }

    public function edit(SalaryStructure $salaryStructure)
    {
        abort_unless($salaryStructure->tenant_id === auth()->user()->tenant_id, 403);

        $salaryStructure->load(['items']);
        $allowanceTemplates = Allowance::active()->orderBy('name')->get();
        $deductionTemplates = Deduction::active()->orderBy('name')->get();

        return view('payroll.salary-structures.edit', compact('salaryStructure', 'allowanceTemplates', 'deductionTemplates'));
    }

    public function update(Request $request, SalaryStructure $salaryStructure)
    {
        abort_unless($salaryStructure->tenant_id === auth()->user()->tenant_id, 403);

        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'basic_salary' => 'required|numeric|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
            'allowances' => 'nullable|array',
            'allowances.*.name' => 'required_with:allowances|string|max:255',
            'allowances.*.amount_type' => 'required_with:allowances|in:fixed,percentage',
            'allowances.*.amount' => 'required_with:allowances|numeric|min:0',
            'allowances.*.is_taxable' => 'nullable|boolean',
            'deductions' => 'nullable|array',
            'deductions.*.name' => 'required_with:deductions|string|max:255',
            'deductions.*.amount_type' => 'required_with:deductions|in:fixed,percentage',
            'deductions.*.amount' => 'required_with:deductions|numeric|min:0',
            'deductions.*.is_taxable' => 'nullable|boolean',
            'change_reason' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            // Snapshot the current state before making changes
            $salaryStructure->load('items');
            $salaryStructure->createVersionSnapshot($validated['change_reason'] ?? null);

            $salaryStructure->update([
                'name' => $validated['name'],
                'basic_salary' => $validated['basic_salary'],
                'effective_from' => $validated['effective_from'],
                'effective_to' => $validated['effective_to'] ?? null,
                'is_active' => $validated['is_active'] ?? $salaryStructure->is_active,
                'notes' => $validated['notes'] ?? null,
                'version' => ($salaryStructure->version ?? 1) + 1,
            ]);

            // Delete existing items and recreate
            $salaryStructure->items()->delete();

            if (! empty($validated['allowances'])) {
                foreach ($validated['allowances'] as $index => $allowance) {
                    if (! empty($allowance['name']) && isset($allowance['amount'])) {
                        $salaryStructure->items()->create([
                            'type' => 'allowance',
                            'name' => $allowance['name'],
                            'amount_type' => $allowance['amount_type'],
                            'amount' => $allowance['amount'],
                            'is_taxable' => $allowance['is_taxable'] ?? true,
                            'sort_order' => $index,
                        ]);
                    }
                }
            }

            if (! empty($validated['deductions'])) {
                foreach ($validated['deductions'] as $index => $deduction) {
                    if (! empty($deduction['name']) && isset($deduction['amount'])) {
                        $salaryStructure->items()->create([
                            'type' => 'deduction',
                            'name' => $deduction['name'],
                            'amount_type' => $deduction['amount_type'],
                            'amount' => $deduction['amount'],
                            'is_taxable' => $deduction['is_taxable'] ?? false,
                            'sort_order' => $index,
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('salary-structures.show', $salaryStructure)
                ->with('success', 'Salary structure updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->withErrors(['error' => 'Failed to update salary structure.']);
        }
    }

    public function destroy(SalaryStructure $salaryStructure)
    {
        abort_unless($salaryStructure->tenant_id === auth()->user()->tenant_id, 403);

        // Check if any payrolls reference this structure
        if ($salaryStructure->payrolls()->exists()) {
            return redirect()->route('salary-structures.index')
                ->with('error', 'Cannot delete salary structure that has been used in payroll.');
        }

        $salaryStructure->delete();

        return redirect()->route('salary-structures.index')
            ->with('success', 'Salary structure deleted successfully.');
    }
}
