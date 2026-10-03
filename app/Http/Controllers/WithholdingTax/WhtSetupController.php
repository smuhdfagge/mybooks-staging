<?php

namespace App\Http\Controllers\WithholdingTax;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\WhtCategory;
use App\Services\Accounting\WithholdingTax;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Withholding tax setup: the business's WHT transaction types and rates
 * (seeded with the statutory rates, editable) and its WHT settings.
 */
class WhtSetupController extends Controller
{
    public function index()
    {
        $tenantId = auth()->user()->tenant_id;
        WhtCategory::seedDefaults($tenantId);

        $categories = WhtCategory::where('tenant_id', $tenantId)->orderBy('sort_order')->orderBy('name')->get();
        $settings = WithholdingTax::settings($tenantId);

        return view('withholding-tax.setup', compact('categories', 'settings'));
    }

    public function updateRates(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $validated = $request->validate([
            'rates' => ['required', 'array'],
            'rates.*.name' => ['required', 'string', 'max:255'],
            'rates.*.rate_company' => ['required', 'numeric', 'min:0', 'max:100'],
            'rates.*.rate_individual' => ['required', 'numeric', 'min:0', 'max:100'],
            'rates.*.effective_from' => ['nullable', 'date'],
            'rates.*.source' => ['nullable', 'string', 'max:255'],
            'rates.*.double_without_tin' => ['boolean'],
            'rates.*.is_active' => ['boolean'],
        ]);

        DB::transaction(function () use ($validated, $tenantId) {
            foreach ($validated['rates'] as $id => $row) {
                $category = WhtCategory::where('tenant_id', $tenantId)->find($id);
                if (! $category) {
                    continue;
                }
                $category->fill([
                    'name' => $row['name'],
                    'rate_company' => $row['rate_company'],
                    'rate_individual' => $row['rate_individual'],
                    'effective_from' => $row['effective_from'] ?? null,
                    'source' => $row['source'] ?? null,
                    'double_without_tin' => (bool) ($row['double_without_tin'] ?? false),
                    'is_active' => (bool) ($row['is_active'] ?? false),
                ]);
                if ($category->isDirty()) {
                    $changes = $category->getDirty();
                    $category->save();
                    ActivityLogService::log('updated', "WHT rate changed: {$category->name}", WhtCategory::class, $category->id, $category->name, $changes);
                }
            }
        });

        return redirect()->route('withholding-tax.setup')->with('success', 'WHT rates saved.');
    }

    public function storeRate(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('wht_categories', 'name')->where('tenant_id', $tenantId)],
            'rate_company' => ['required', 'numeric', 'min:0', 'max:100'],
            'rate_individual' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_from' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:255'],
            'double_without_tin' => ['boolean'],
        ]);

        $code = Str::limit(Str::slug($validated['name'], '_'), 40, '');
        while (WhtCategory::where('tenant_id', $tenantId)->where('code', $code)->exists()) {
            $code = Str::limit(Str::slug($validated['name'], '_'), 40, '').'_'.Str::lower(Str::random(4));
        }

        $category = WhtCategory::create($validated + [
            'tenant_id' => $tenantId,
            'code' => $code,
            'double_without_tin' => (bool) ($validated['double_without_tin'] ?? false),
            'is_active' => true,
            'sort_order' => (int) WhtCategory::where('tenant_id', $tenantId)->max('sort_order') + 10,
        ]);
        ActivityLogService::log('created', "WHT transaction type added: {$category->name}", WhtCategory::class, $category->id, $category->name, $validated);

        return redirect()->route('withholding-tax.setup')->with('success', 'WHT transaction type added.');
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'business_type' => ['required', Rule::in(['company', 'individual'])],
            'small_company' => ['boolean'],
            'small_company_threshold' => ['required', 'numeric', 'min:0'],
        ]);

        $tenant = Tenant::findOrFail(auth()->user()->tenant_id);
        $settings = $tenant->settings ?? [];
        $settings['wht'] = [
            'business_type' => $validated['business_type'],
            'small_company' => (bool) ($validated['small_company'] ?? false),
            'small_company_threshold' => round((float) $validated['small_company_threshold'], 2),
        ];
        $tenant->settings = $settings;
        $tenant->save();

        return redirect()->route('withholding-tax.setup')->with('success', 'WHT settings saved.');
    }
}
