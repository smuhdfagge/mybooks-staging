<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\ChartOfAccount;
use App\Models\Import;
use App\Services\BudgetService;
use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BudgetController extends Controller
{
    protected BudgetService $budgetService;

    public function __construct(BudgetService $budgetService)
    {
        $this->budgetService = $budgetService;
    }

    public function index()
    {
        return view('budgets.index');
    }

    public function create()
    {
        $accounts = ChartOfAccount::where('is_active', true)
            ->whereIn('type', ['income', 'expense'])
            ->orderBy('account_code')
            ->get()
            ->groupBy('type');
        
        $fiscalYears = Budget::getFiscalYears();
        $existingBudgets = Budget::select('id', 'name', 'fiscal_year')
            ->orderBy('fiscal_year', 'desc')
            ->get();

        return view('budgets.create', compact('accounts', 'fiscalYears', 'existingBudgets'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'fiscal_year' => 'required|string|size:4',
            'description' => 'nullable|string|max:1000',
            'copy_from' => ['nullable', Rule::exists('budgets', 'id')->where('tenant_id', $tenantId)],
            'lines' => 'nullable|array',
            'lines.*.account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'lines.*.jan' => 'nullable|numeric|min:0',
            'lines.*.feb' => 'nullable|numeric|min:0',
            'lines.*.mar' => 'nullable|numeric|min:0',
            'lines.*.apr' => 'nullable|numeric|min:0',
            'lines.*.may' => 'nullable|numeric|min:0',
            'lines.*.jun' => 'nullable|numeric|min:0',
            'lines.*.jul' => 'nullable|numeric|min:0',
            'lines.*.aug' => 'nullable|numeric|min:0',
            'lines.*.sep' => 'nullable|numeric|min:0',
            'lines.*.oct' => 'nullable|numeric|min:0',
            'lines.*.nov' => 'nullable|numeric|min:0',
            'lines.*.dec' => 'nullable|numeric|min:0',
            'lines.*.notes' => 'nullable|string|max:500',
        ]);

        // Check for duplicate
        $exists = Budget::where('fiscal_year', $validated['fiscal_year'])
            ->where('name', $validated['name'])
            ->exists();
        
        if ($exists) {
            return back()->withErrors(['name' => 'A budget with this name already exists for the selected fiscal year.'])->withInput();
        }

        // If copying from existing budget
        if (!empty($validated['copy_from'])) {
            $sourceBudget = Budget::findOrFail($validated['copy_from']);
            $budget = $this->budgetService->copyFromPreviousYear(
                $sourceBudget,
                $validated['fiscal_year'],
                $validated['name']
            );
            
            if (!empty($validated['description'])) {
                $budget->update(['description' => $validated['description']]);
            }

            return redirect()->route('budgets.edit', $budget)
                ->with('success', 'Budget created from template. You can now adjust the amounts.');
        }

        DB::transaction(function () use ($validated) {
            $budget = Budget::create([
                'tenant_id' => auth()->user()->tenant_id,
                'name' => $validated['name'],
                'fiscal_year' => $validated['fiscal_year'],
                'description' => $validated['description'] ?? null,
                'status' => Budget::STATUS_DRAFT,
                'created_by' => auth()->id(),
            ]);

            if (!empty($validated['lines'])) {
                foreach ($validated['lines'] as $lineData) {
                    $line = new BudgetLine([
                        'budget_id' => $budget->id,
                        'account_id' => $lineData['account_id'],
                        'jan' => $lineData['jan'] ?? 0,
                        'feb' => $lineData['feb'] ?? 0,
                        'mar' => $lineData['mar'] ?? 0,
                        'apr' => $lineData['apr'] ?? 0,
                        'may' => $lineData['may'] ?? 0,
                        'jun' => $lineData['jun'] ?? 0,
                        'jul' => $lineData['jul'] ?? 0,
                        'aug' => $lineData['aug'] ?? 0,
                        'sep' => $lineData['sep'] ?? 0,
                        'oct' => $lineData['oct'] ?? 0,
                        'nov' => $lineData['nov'] ?? 0,
                        'dec' => $lineData['dec'] ?? 0,
                        'notes' => $lineData['notes'] ?? null,
                    ]);
                    $line->calculateAnnualTotal();
                    $line->save();
                }
            }
        });

        return redirect()->route('budgets.index')->with('success', 'Budget created successfully.');
    }

    public function show(Budget $budget)
    {
        $budget->load(['lines.account', 'createdBy', 'approvedBy']);
        $summary = $this->budgetService->getBudgetSummary($budget);
        $months = Budget::getMonthColumns();

        // Get budget vs actual if budget is active
        $comparison = null;
        $ytdUtilization = null;
        if ($budget->isActive() || $budget->isLocked()) {
            $comparison = $this->budgetService->getBudgetVsActual($budget);
            $ytdUtilization = $this->budgetService->getYTDUtilization($budget);
        }

        return view('budgets.show', compact('budget', 'summary', 'months', 'comparison', 'ytdUtilization'));
    }

    public function edit(Budget $budget)
    {
        if ($budget->isLocked()) {
            return redirect()->route('budgets.show', $budget)
                ->with('error', 'This budget is locked and cannot be edited.');
        }

        $budget->load('lines.account');
        
        $accounts = ChartOfAccount::where('is_active', true)
            ->whereIn('type', ['income', 'expense'])
            ->orderBy('account_code')
            ->get()
            ->groupBy('type');
        
        $fiscalYears = Budget::getFiscalYears();
        $months = Budget::getMonthColumns();
        
        // Get existing line account IDs for the form
        $existingAccountIds = $budget->lines->pluck('account_id')->toArray();
        
        // Pre-format budget lines for Alpine.js
        $budgetLinesJson = $budget->lines->map(function($line) {
            return [
                'key' => 'line_' . $line->id,
                'id' => $line->id,
                'account_id' => $line->account_id,
                'account_code' => $line->account->account_code,
                'account_name' => $line->account->name,
                'jan' => floatval($line->jan),
                'feb' => floatval($line->feb),
                'mar' => floatval($line->mar),
                'apr' => floatval($line->apr),
                'may' => floatval($line->may),
                'jun' => floatval($line->jun),
                'jul' => floatval($line->jul),
                'aug' => floatval($line->aug),
                'sep' => floatval($line->sep),
                'oct' => floatval($line->oct),
                'nov' => floatval($line->nov),
                'dec' => floatval($line->dec),
                'annual_total' => floatval($line->annual_total),
            ];
        })->values();

        return view('budgets.edit', compact('budget', 'accounts', 'fiscalYears', 'months', 'existingAccountIds', 'budgetLinesJson'));
    }

    public function update(Request $request, Budget $budget)
    {
        if ($budget->isLocked()) {
            return back()->with('error', 'This budget is locked and cannot be edited.');
        }

        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'lines' => 'nullable|array',
            'lines.*.id' => ['nullable', Rule::exists('budget_lines', 'id')->where('budget_id', $budget->id)],
            'lines.*.account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'lines.*.jan' => 'nullable|numeric|min:0',
            'lines.*.feb' => 'nullable|numeric|min:0',
            'lines.*.mar' => 'nullable|numeric|min:0',
            'lines.*.apr' => 'nullable|numeric|min:0',
            'lines.*.may' => 'nullable|numeric|min:0',
            'lines.*.jun' => 'nullable|numeric|min:0',
            'lines.*.jul' => 'nullable|numeric|min:0',
            'lines.*.aug' => 'nullable|numeric|min:0',
            'lines.*.sep' => 'nullable|numeric|min:0',
            'lines.*.oct' => 'nullable|numeric|min:0',
            'lines.*.nov' => 'nullable|numeric|min:0',
            'lines.*.dec' => 'nullable|numeric|min:0',
            'lines.*.notes' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($validated, $budget) {
            $budget->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            // Get existing line IDs
            $existingLineIds = $budget->lines->pluck('id')->toArray();
            $updatedLineIds = [];

            if (!empty($validated['lines'])) {
                foreach ($validated['lines'] as $lineData) {
                    $lineAttributes = [
                        'account_id' => $lineData['account_id'],
                        'jan' => $lineData['jan'] ?? 0,
                        'feb' => $lineData['feb'] ?? 0,
                        'mar' => $lineData['mar'] ?? 0,
                        'apr' => $lineData['apr'] ?? 0,
                        'may' => $lineData['may'] ?? 0,
                        'jun' => $lineData['jun'] ?? 0,
                        'jul' => $lineData['jul'] ?? 0,
                        'aug' => $lineData['aug'] ?? 0,
                        'sep' => $lineData['sep'] ?? 0,
                        'oct' => $lineData['oct'] ?? 0,
                        'nov' => $lineData['nov'] ?? 0,
                        'dec' => $lineData['dec'] ?? 0,
                        'notes' => $lineData['notes'] ?? null,
                    ];

                    if (!empty($lineData['id'])) {
                        // Update existing line
                        $line = BudgetLine::find($lineData['id']);
                        if ($line && $line->budget_id === $budget->id) {
                            $line->fill($lineAttributes);
                            $line->calculateAnnualTotal();
                            $line->save();
                            $updatedLineIds[] = $line->id;
                        }
                    } else {
                        // Create new line
                        $line = new BudgetLine($lineAttributes);
                        $line->budget_id = $budget->id;
                        $line->calculateAnnualTotal();
                        $line->save();
                        $updatedLineIds[] = $line->id;
                    }
                }
            }

            // Delete removed lines
            $linesToDelete = array_diff($existingLineIds, $updatedLineIds);
            if (!empty($linesToDelete)) {
                BudgetLine::whereIn('id', $linesToDelete)->delete();
            }
        });

        return redirect()->route('budgets.show', $budget)->with('success', 'Budget updated successfully.');
    }

    public function destroy(Budget $budget)
    {
        if ($budget->isLocked()) {
            return back()->with('error', 'Locked budgets cannot be deleted.');
        }

        $budget->lines()->delete();
        $budget->delete();

        return redirect()->route('budgets.index')->with('success', 'Budget deleted successfully.');
    }

    public function activate(Budget $budget)
    {
        if ($budget->isLocked()) {
            return back()->with('error', 'This budget is already locked.');
        }

        if ($budget->lines()->count() === 0) {
            return back()->with('error', 'Cannot activate a budget with no line items.');
        }

        $budget->activate();

        return back()->with('success', 'Budget activated successfully.');
    }

    public function lock(Budget $budget)
    {
        if (!$budget->isActive()) {
            return back()->with('error', 'Only active budgets can be locked.');
        }

        $budget->lock();

        return back()->with('success', 'Budget locked successfully.');
    }

    public function vsActual(Budget $budget)
    {
        $budget->load(['lines.account']);
        $months = Budget::getMonthColumns();
        
        $comparison = $this->budgetService->getBudgetVsActual($budget);
        $ytdUtilization = $this->budgetService->getYTDUtilization($budget);
        $summary = $this->budgetService->getBudgetSummary($budget);

        return view('budgets.vs-actual', compact('budget', 'comparison', 'ytdUtilization', 'summary', 'months'));
    }

    /**
     * Import budget line items from CSV/XLSX
     */
    public function import(Request $request, Budget $budget)
    {
        if ($budget->isLocked()) {
            return back()->with('error', 'Cannot import lines into a locked budget.');
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:' . config('mybooks.import_max_file_size', 10240),
            'update_existing' => 'nullable|boolean',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $format = in_array($extension, ['xlsx', 'xls']) ? Import::FORMAT_XLSX : Import::FORMAT_CSV;

        // Store the file
        $filename = Str::uuid() . '.' . $extension;
        $path = auth()->user()->tenant_id . '/' . $filename;
        Storage::disk('imports')->put($path, file_get_contents($file));

        // Create import record
        $import = Import::create([
            'tenant_id' => auth()->user()->tenant_id,
            'user_id' => auth()->id(),
            'type' => Import::TYPE_BUDGET_LINES,
            'format' => $format,
            'status' => Import::STATUS_PROCESSING,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'options' => [
                'budget_id' => $budget->id,
                'skip_duplicates' => !$request->boolean('update_existing'),
                'update_existing' => $request->boolean('update_existing'),
            ],
        ]);

        // Process the import
        $importService = app(ImportService::class);
        $importService->processImport($import);

        // Clean up the file
        Storage::disk('imports')->delete($path);

        if ($import->status === Import::STATUS_COMPLETED) {
            $message = "{$import->successful_rows} line(s) imported successfully.";
            if ($import->failed_rows > 0) {
                $message .= " {$import->failed_rows} failed.";
            }
            if ($import->skipped_rows > 0) {
                $message .= " {$import->skipped_rows} skipped (duplicates).";
            }
            if (!empty($import->warnings)) {
                $message .= ' ' . implode(' ', $import->warnings);
            }
            return redirect()->route('budgets.edit', $budget)->with('success', $message);
        }

        return redirect()->route('budgets.edit', $budget)->with('error', 'Import failed: ' . $import->error_message);
    }

    /**
     * Download sample import template for budget line items
     */
    public function importTemplate()
    {
        $sampleData = ImportService::getSampleData(Import::TYPE_BUDGET_LINES);
        $headers = array_keys($sampleData[0]);
        $filename = 'budget_lines_template.csv';

        $callback = function () use ($sampleData, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            foreach ($sampleData as $row) {
                fputcsv($file, array_values($row));
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
