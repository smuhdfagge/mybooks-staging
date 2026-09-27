<?php

namespace App\Http\Controllers;

use App\Models\RecurrentExpense;
use App\Models\Vendor;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecurrentExpenseController extends Controller
{
    public function index()
    {
        return view('recurrent-expenses.index');
    }

    public function create()
    {
        $vendors = Vendor::where('is_active', true)->get();
        $expenseAccounts = ChartOfAccount::where('type', 'expense')->where('is_active', true)->get();
        $paymentAccounts = ChartOfAccount::whereIn('sub_type', ['bank', 'cash'])->where('is_active', true)->get();
        
        return view('recurrent-expenses.create', compact('vendors', 'expenseAccounts', 'paymentAccounts'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'profile_name' => 'required|string|max:255',
            'expense_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'amount' => 'required|numeric|min:0.01',
            'vendor_id' => ['nullable', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'paid_through_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'frequency' => 'required|in:weekly,monthly,quarterly,yearly',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'description' => 'nullable|string',
        ]);
        
        $recurrentExpense = RecurrentExpense::create([
            'tenant_id' => $tenantId,
            'profile_name' => $validated['profile_name'],
            'expense_account_id' => $validated['expense_account_id'],
            'amount' => $validated['amount'],
            'vendor_id' => $validated['vendor_id'] ?? null,
            'paid_through_id' => $validated['paid_through_id'] ?? null,
            'frequency' => $validated['frequency'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'next_expense_date' => $validated['start_date'],
            'description' => $validated['description'] ?? null,
            'status' => 'active',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('recurrent-expenses.show', $recurrentExpense)->with('success', 'Recurrent expense profile created.');
    }

    public function show(RecurrentExpense $recurrentExpense)
    {
        $recurrentExpense->load(['vendor', 'expenseAccount', 'paidThroughAccount', 'expenses']);
        return view('recurrent-expenses.show', compact('recurrentExpense'));
    }

    public function edit(RecurrentExpense $recurrentExpense)
    {
        $vendors = Vendor::where('is_active', true)->get();
        $expenseAccounts = ChartOfAccount::where('type', 'expense')->where('is_active', true)->get();
        $paymentAccounts = ChartOfAccount::whereIn('sub_type', ['bank', 'cash'])->where('is_active', true)->get();
        
        return view('recurrent-expenses.edit', compact('recurrentExpense', 'vendors', 'expenseAccounts', 'paymentAccounts'));
    }

    public function update(Request $request, RecurrentExpense $recurrentExpense)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'profile_name' => 'required|string|max:255',
            'expense_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'amount' => 'required|numeric|min:0.01',
            'vendor_id' => ['nullable', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'paid_through_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'frequency' => 'required|in:weekly,monthly,quarterly,yearly',
            'end_date' => 'nullable|date|after:start_date',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,paused,stopped',
        ]);

        $recurrentExpense->update([
            'profile_name' => $validated['profile_name'],
            'expense_account_id' => $validated['expense_account_id'],
            'amount' => $validated['amount'],
            'vendor_id' => $validated['vendor_id'] ?? null,
            'paid_through_id' => $validated['paid_through_id'] ?? null,
            'frequency' => $validated['frequency'],
            'end_date' => $validated['end_date'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? $recurrentExpense->status,
        ]);

        return redirect()->route('recurrent-expenses.show', $recurrentExpense)->with('success', 'Recurrent expense profile updated.');
    }

    public function destroy(RecurrentExpense $recurrentExpense)
    {
        $recurrentExpense->delete();
        return redirect()->route('recurrent-expenses.index')->with('success', 'Recurrent expense profile deleted.');
    }

    public function toggleStatus(RecurrentExpense $recurrentExpense)
    {
        $newStatus = $recurrentExpense->status === 'active' ? 'paused' : 'active';
        $recurrentExpense->update(['status' => $newStatus]);
        $label = $newStatus === 'active' ? 'activated' : 'paused';
        return redirect()->route('recurrent-expenses.show', $recurrentExpense)->with('success', "Profile {$label}.");
    }
}
