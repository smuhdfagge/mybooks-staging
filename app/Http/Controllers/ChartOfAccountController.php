<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChartOfAccountController extends Controller
{
    public function index()
    {
        return view('chart-of-accounts.index');
    }

    public function create()
    {
        $accounts = ChartOfAccount::where('is_active', true)->get();
        $types = ChartOfAccount::getTypes();

        return view('chart-of-accounts.create', compact('accounts', 'types'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'account_code' => 'required|string|max:20',
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,income,expense',
            'sub_type' => 'nullable|string|max:100',
            'parent_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'description' => 'nullable|string',
            'opening_balance' => 'nullable|numeric',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['current_balance'] = $validated['opening_balance'] ?? 0;

        ChartOfAccount::create($validated);

        return redirect()->route('chart-of-accounts.index')->with('success', 'Account created successfully.');
    }

    public function show(ChartOfAccount $chartOfAccount)
    {
        $chartOfAccount->load(['parent', 'children', 'journalEntries.journal']);

        return view('chart-of-accounts.show', compact('chartOfAccount'));
    }

    public function edit(ChartOfAccount $chartOfAccount)
    {
        if ($chartOfAccount->is_system) {
            return redirect()->back()->with('error', 'System accounts cannot be edited.');
        }

        $accounts = ChartOfAccount::where('is_active', true)
            ->where('id', '!=', $chartOfAccount->id)
            ->get();
        $types = ChartOfAccount::getTypes();

        return view('chart-of-accounts.edit', compact('chartOfAccount', 'accounts', 'types'));
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount)
    {
        if ($chartOfAccount->is_system) {
            return redirect()->back()->with('error', 'System accounts cannot be modified.');
        }

        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'account_code' => 'required|string|max:20',
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,income,expense',
            'sub_type' => 'nullable|string|max:100',
            'parent_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $chartOfAccount->update($validated);

        return redirect()->route('chart-of-accounts.index')->with('success', 'Account updated successfully.');
    }

    public function destroy(ChartOfAccount $chartOfAccount)
    {
        if ($chartOfAccount->is_system) {
            return redirect()->back()->with('error', 'System accounts cannot be deleted.');
        }

        if ($chartOfAccount->journalEntries()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete account with journal entries.');
        }

        $chartOfAccount->delete();

        return redirect()->route('chart-of-accounts.index')->with('success', 'Account deleted successfully.');
    }
}
