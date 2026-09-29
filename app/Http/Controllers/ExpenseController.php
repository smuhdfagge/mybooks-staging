<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Models\Bank;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\Vendor;
use App\Services\BankService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function __construct(
        protected BankService $bankService
    ) {}

    public function index()
    {
        return view('expenses.index');
    }

    public function create()
    {
        $vendors = Vendor::where('is_active', true)->get();
        $expenseAccounts = ChartOfAccount::where('type', 'expense')->where('is_active', true)->get();
        $paymentAccounts = ChartOfAccount::where('is_active', true)->orderBy('account_code')->get();
        $expenseNumber = Expense::previewNumber(auth()->user()->tenant_id);
        $banks = Bank::where('is_active', true)->orderBy('name')->get();

        return view('expenses.create', compact('vendors', 'expenseAccounts', 'paymentAccounts', 'expenseNumber', 'banks'));
    }

    public function store(StoreExpenseRequest $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validated();

        $amount = $validated['amount'];
        $taxAmount = 0; // Can be added to form if needed
        $total = $amount + $taxAmount;

        $expense = Expense::create([
            'tenant_id' => $tenantId,
            'expense_number' => Expense::generateNumber($tenantId),
            'name' => $validated['name'],
            'expense_date' => $validated['expense_date'],
            'expense_account_id' => $validated['expense_account_id'],
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'vendor_id' => $validated['vendor_id'] ?? null,
            'paid_through_id' => $validated['paid_through_id'] ?? null,
            'bank_id' => $validated['bank_id'] ?? null,
            'reference' => $validated['reference'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_billable' => $validated['is_billable'] ?? false,
            'created_by' => auth()->id(),
            'status' => Expense::STATUS_DRAFT, // Always start as draft
        ]);

        // Note: Bank balance is NOT updated here - only when expense is marked as paid

        return redirect()->route('expenses.show', $expense)->with('success', 'Expense recorded as draft. Submit for approval to process payment.');
    }

    public function show(Expense $expense)
    {
        $expense->load(['vendor', 'expenseAccount', 'paidThroughAccount', 'journal.entries.account', 'approvedByUser', 'rejectedByUser', 'createdBy']);

        return view('expenses.show', compact('expense'));
    }

    public function edit(Expense $expense)
    {
        // Only allow editing if expense is in draft or rejected status
        if (! $expense->canBeEdited()) {
            return redirect()->route('expenses.show', $expense)
                ->with('error', 'This expense cannot be edited in its current status.');
        }

        $vendors = Vendor::where('is_active', true)->get();
        $expenseAccounts = ChartOfAccount::where('type', 'expense')->where('is_active', true)->get();
        $paymentAccounts = ChartOfAccount::where('is_active', true)->orderBy('account_code')->get();
        $banks = Bank::where('is_active', true)->orderBy('name')->get();

        return view('expenses.edit', compact('expense', 'vendors', 'expenseAccounts', 'paymentAccounts', 'banks'));
    }

    public function update(StoreExpenseRequest $request, Expense $expense)
    {
        // Only allow updating if expense is in draft or rejected status
        if (! $expense->canBeEdited()) {
            return redirect()->route('expenses.show', $expense)
                ->with('error', 'This expense cannot be updated in its current status.');
        }

        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validated();

        $validated['total'] = $validated['amount']; // Using amount as total for simplicity

        // Reset to draft status if it was rejected
        if ($expense->isRejected()) {
            $validated['status'] = Expense::STATUS_DRAFT;
            $validated['rejection_reason'] = null;
            $validated['rejected_by'] = null;
            $validated['rejected_at'] = null;
        }

        $expense->update($validated);

        return redirect()->route('expenses.show', $expense)->with('success', 'Expense updated.');
    }

    /**
     * Submit expense for approval
     */
    public function submit(Expense $expense)
    {
        if (! $expense->canBeSubmitted()) {
            return redirect()->route('expenses.show', $expense)
                ->with('error', 'This expense cannot be submitted for approval in its current status.');
        }

        $expense->submitForApproval();

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense submitted for approval.');
    }

    /**
     * Approve expense (admin only)
     */
    public function approve(Expense $expense)
    {
        if (! $expense->canBeApprovedBy(auth()->user())) {
            return redirect()->route('expenses.show', $expense)
                ->with('error', 'Only an admin who did not raise this expense can approve it.');
        }

        if (! $expense->canBeApproved()) {
            return redirect()->route('expenses.show', $expense)
                ->with('error', 'This expense cannot be approved in its current status.');
        }

        $expense->approve(auth()->id());

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense approved successfully.');
    }

    /**
     * Reject expense (admin only)
     */
    public function reject(Request $request, Expense $expense)
    {
        if (! $expense->canBeRejectedBy(auth()->user())) {
            return redirect()->route('expenses.show', $expense)
                ->with('error', 'You do not have permission to reject expenses.');
        }

        if (! $expense->canBeRejected()) {
            return redirect()->route('expenses.show', $expense)
                ->with('error', 'This expense cannot be rejected in its current status.');
        }

        $request->validate([
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $expense->reject(auth()->id(), $request->input('rejection_reason'));

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense rejected and sent back for review.');
    }

    /**
     * Mark expense as paid (admin only, after approval)
     */
    public function markAsPaid(Expense $expense)
    {
        // Check if user has admin role or is super admin
        if (! auth()->user()->hasRole('admin') && ! auth()->user()->isSuperAdmin()) {
            return redirect()->route('expenses.show', $expense)
                ->with('error', 'You do not have permission to mark expenses as paid.');
        }

        if (! $expense->canBeMarkedAsPaid()) {
            return redirect()->route('expenses.show', $expense)
                ->with('error', 'This expense must be approved before it can be marked as paid.');
        }

        DB::transaction(function () use ($expense) {
            $expense->markAsPaid();
        });

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense marked as paid. Journal entries and account balances have been updated.');
    }

    public function destroy(Expense $expense)
    {
        // Only allow deletion if expense is draft or rejected
        if (! in_array($expense->status, [Expense::STATUS_DRAFT, Expense::STATUS_REJECTED])) {
            return redirect()->route('expenses.show', $expense)
                ->with('error', 'Only draft or rejected expenses can be deleted.');
        }

        DB::transaction(function () use ($expense) {
            $expense->delete();
        });

        return redirect()->route('expenses.index')->with('success', 'Expense deleted.');
    }
}
