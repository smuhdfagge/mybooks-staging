<?php

namespace App\Http\Controllers;

use App\Models\FixedAssetCategory;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FixedAssetCategoryController extends Controller
{
    public function index()
    {
        return view('categories.index');
    }

    public function create()
    {
        $tenantId = auth()->user()->tenant_id;

        $assetAccounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('type', 'asset')
            ->orderBy('account_code')
            ->get();

        $contraAssetAccounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->whereIn('type', ['asset', 'contra_asset'])
            ->orderBy('account_code')
            ->get();

        $expenseAccounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('type', 'expense')
            ->orderBy('account_code')
            ->get();

        $otherAccounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->whereIn('type', ['income', 'expense', 'other_income', 'other_expense'])
            ->orderBy('account_code')
            ->get();

        return view('categories.create', compact(
            'assetAccounts', 'contraAssetAccounts', 'expenseAccounts', 'otherAccounts'
        ));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'default_useful_life' => 'nullable|numeric|min:0.5|max:50',
            'default_depreciation_method' => 'nullable|in:straight_line,declining_balance,double_declining,sum_of_years',
            'asset_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'accumulated_depreciation_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'depreciation_expense_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'gain_loss_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
        ]);

        $validated['tenant_id'] = $tenantId;

        FixedAssetCategory::create($validated);

        return redirect()->route('fixed-asset-categories.index')
            ->with('success', 'Asset category created successfully.');
    }

    public function show(FixedAssetCategory $fixedAssetCategory)
    {
        $fixedAssetCategory->load([
            'assetAccount', 
            'accumulatedDepreciationAccount', 
            'depreciationExpenseAccount',
            'gainLossAccount',
            'assets'
        ]);

        return view('categories.show', ['category' => $fixedAssetCategory]);
    }

    public function edit(FixedAssetCategory $fixedAssetCategory)
    {
        $tenantId = auth()->user()->tenant_id;

        $assetAccounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('type', 'asset')
            ->orderBy('account_code')
            ->get();

        $contraAssetAccounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->whereIn('type', ['asset', 'contra_asset'])
            ->orderBy('account_code')
            ->get();

        $expenseAccounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('type', 'expense')
            ->orderBy('account_code')
            ->get();

        $otherAccounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->whereIn('type', ['income', 'expense', 'other_income', 'other_expense'])
            ->orderBy('account_code')
            ->get();

        return view('categories.edit', [
            'category' => $fixedAssetCategory,
            'assetAccounts' => $assetAccounts,
            'contraAssetAccounts' => $contraAssetAccounts,
            'expenseAccounts' => $expenseAccounts,
            'otherAccounts' => $otherAccounts,
        ]);
    }

    public function update(Request $request, FixedAssetCategory $fixedAssetCategory)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'default_useful_life' => 'nullable|numeric|min:0.5|max:50',
            'default_depreciation_method' => 'nullable|in:straight_line,declining_balance,double_declining,sum_of_years',
            'asset_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'accumulated_depreciation_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'depreciation_expense_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'gain_loss_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
        ]);

        $fixedAssetCategory->update($validated);

        return redirect()->route('fixed-asset-categories.show', $fixedAssetCategory)
            ->with('success', 'Asset category updated successfully.');
    }

    public function destroy(FixedAssetCategory $fixedAssetCategory)
    {
        if ($fixedAssetCategory->assets()->exists()) {
            return back()->with('error', 'Cannot delete category with associated assets.');
        }

        $fixedAssetCategory->delete();

        return redirect()->route('fixed-asset-categories.index')
            ->with('success', 'Asset category deleted successfully.');
    }
}
