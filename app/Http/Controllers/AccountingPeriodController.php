<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\Journal;
use App\Services\YearEndCloseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingPeriodController extends Controller
{
    public function index()
    {
        $periods = AccountingPeriod::orderBy('start_date', 'desc')->get();
        $fiscalYears = AccountingPeriod::distinct()->pluck('fiscal_year')->filter()->sort()->reverse();

        return view('accounting-periods.index', compact('periods', 'fiscalYears'));
    }

    public function create()
    {
        return view('accounting-periods.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'fiscal_year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $tenantId = auth()->user()->tenant_id;

        // Check for overlapping periods
        $overlap = AccountingPeriod::where('tenant_id', $tenantId)
            ->where(function ($query) use ($validated) {
                $query->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                    ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                    ->orWhere(function ($q) use ($validated) {
                        $q->where('start_date', '<=', $validated['start_date'])
                            ->where('end_date', '>=', $validated['end_date']);
                    });
            })
            ->exists();

        if ($overlap) {
            return back()->withInput()->withErrors(['dates' => 'This period overlaps with an existing accounting period.']);
        }

        AccountingPeriod::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            // Optional in the form; default to the start date's year (O4).
            'fiscal_year' => $validated['fiscal_year'] ?? (int) date('Y', (int) strtotime($validated['start_date'])),
            'status' => AccountingPeriod::STATUS_OPEN,
        ]);

        return redirect()->route('accounting-periods.index')->with('success', 'Accounting period created successfully.');
    }

    public function show(AccountingPeriod $accountingPeriod)
    {
        // Get summary of transactions in this period
        $summary = $this->getPeriodSummary($accountingPeriod);

        return view('accounting-periods.show', compact('accountingPeriod', 'summary'));
    }

    public function edit(AccountingPeriod $accountingPeriod)
    {
        if ($accountingPeriod->isLocked()) {
            return redirect()->route('accounting-periods.show', $accountingPeriod)
                ->with('error', 'Locked periods cannot be edited.');
        }

        return view('accounting-periods.edit', compact('accountingPeriod'));
    }

    public function update(Request $request, AccountingPeriod $accountingPeriod)
    {
        if ($accountingPeriod->isLocked()) {
            return redirect()->route('accounting-periods.show', $accountingPeriod)
                ->with('error', 'Locked periods cannot be edited.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'fiscal_year' => 'nullable|integer|min:2000|max:2100',
        ]);

        // Only allow name and fiscal year changes if period is closed
        if ($accountingPeriod->isClosed()) {
            $accountingPeriod->update([
                'name' => $validated['name'],
                'fiscal_year' => $validated['fiscal_year'],
            ]);
        } else {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'fiscal_year' => 'nullable|integer|min:2000|max:2100',
            ]);

            $accountingPeriod->update($validated);
        }

        return redirect()->route('accounting-periods.show', $accountingPeriod)
            ->with('success', 'Accounting period updated successfully.');
    }

    public function destroy(AccountingPeriod $accountingPeriod)
    {
        if ($accountingPeriod->isClosed()) {
            return redirect()->route('accounting-periods.index')
                ->with('error', 'Closed periods cannot be deleted.');
        }

        $accountingPeriod->delete();

        return redirect()->route('accounting-periods.index')
            ->with('success', 'Accounting period deleted successfully.');
    }

    /**
     * Close an accounting period
     */
    public function close(Request $request, AccountingPeriod $accountingPeriod)
    {
        if ($accountingPeriod->isClosed()) {
            return redirect()->route('accounting-periods.show', $accountingPeriod)
                ->with('error', 'This period is already closed.');
        }

        $validated = $request->validate([
            'closing_notes' => 'nullable|string|max:1000',
            'confirm' => 'required|accepted',
        ]);

        DB::transaction(function () use ($accountingPeriod, $validated) {
            $accountingPeriod->close($validated['closing_notes'] ?? null);
        });

        return redirect()->route('accounting-periods.show', $accountingPeriod)
            ->with('success', 'Accounting period closed successfully. Transactions in this period can no longer be modified.');
    }

    /**
     * Reopen a closed accounting period
     */
    public function reopen(AccountingPeriod $accountingPeriod)
    {
        if ($accountingPeriod->isLocked()) {
            return redirect()->route('accounting-periods.show', $accountingPeriod)
                ->with('error', 'Locked periods cannot be reopened. This is a permanent year-end closing.');
        }

        if (! $accountingPeriod->isClosed()) {
            return redirect()->route('accounting-periods.show', $accountingPeriod)
                ->with('error', 'This period is already open.');
        }

        $accountingPeriod->reopen();

        return redirect()->route('accounting-periods.show', $accountingPeriod)
            ->with('success', 'Accounting period reopened successfully.');
    }

    /**
     * Permanently lock a period (year-end) with closing journal entries
     */
    public function lock(Request $request, AccountingPeriod $accountingPeriod)
    {
        if ($accountingPeriod->isLocked()) {
            return redirect()->route('accounting-periods.show', $accountingPeriod)
                ->with('error', 'This period is already locked.');
        }

        $validated = $request->validate([
            'closing_notes' => 'nullable|string|max:1000',
            'confirm' => 'required|accepted',
        ]);

        try {
            $yearEndService = app(YearEndCloseService::class);
            $result = $yearEndService->performYearEndClose(
                $accountingPeriod,
                $validated['closing_notes'] ?? null
            );

            $message = sprintf(
                'Year-end close completed. Net income: %s, %d closing journal(s) created. Period permanently locked.',
                number_format($result['net_income'], 2),
                $result['journals_created']
            );

            return redirect()->route('accounting-periods.show', $accountingPeriod)
                ->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->route('accounting-periods.show', $accountingPeriod)
                ->with('error', 'Year-end close failed: '.$e->getMessage());
        }
    }

    /**
     * Generate periods for a fiscal year
     */
    public function generatePeriods(Request $request)
    {
        $validated = $request->validate([
            'fiscal_year' => 'required|integer|min:2000|max:2100',
            'start_month' => 'required|integer|min:1|max:12',
        ]);

        $tenantId = auth()->user()->tenant_id;

        // Check if periods already exist for this year
        $existing = AccountingPeriod::where('tenant_id', $tenantId)
            ->where('fiscal_year', $validated['fiscal_year'])
            ->exists();

        if ($existing) {
            return back()->withErrors(['fiscal_year' => 'Periods for this fiscal year already exist.']);
        }

        AccountingPeriod::generateMonthlyPeriods(
            $tenantId,
            $validated['fiscal_year'],
            $validated['start_month']
        );

        return redirect()->route('accounting-periods.index')
            ->with('success', 'Monthly periods generated for fiscal year '.$validated['fiscal_year']);
    }

    /**
     * Get summary of transactions in a period
     */
    protected function getPeriodSummary(AccountingPeriod $accountingPeriod): array
    {
        $tenantId = $accountingPeriod->tenant_id;
        $startDate = $accountingPeriod->start_date;
        $endDate = $accountingPeriod->end_date;

        return [
            'invoices' => DB::table('invoices')
                ->where('tenant_id', $tenantId)
                ->whereBetween('invoice_date', [$startDate, $endDate])
                ->whereNull('deleted_at')
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
                ->first(),

            'bills' => DB::table('bills')
                ->where('tenant_id', $tenantId)
                ->whereBetween('bill_date', [$startDate, $endDate])
                ->whereNull('deleted_at')
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
                ->first(),

            'expenses' => DB::table('expenses')
                ->where('tenant_id', $tenantId)
                ->whereBetween('expense_date', [$startDate, $endDate])
                ->whereNull('deleted_at')
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
                ->first(),

            'payments_received' => DB::table('payments_received')
                ->where('tenant_id', $tenantId)
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
                ->first(),

            'payments_made' => DB::table('payments_made')
                ->where('tenant_id', $tenantId)
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
                ->first(),

            'journals' => DB::table('journals')
                ->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$startDate, $endDate])
                ->whereNull('deleted_at')
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_debit), 0) as total_debit')
                ->first(),
        ];
    }
}
