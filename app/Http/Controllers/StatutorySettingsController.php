<?php

namespace App\Http\Controllers;

use App\Models\Allowance;
use App\Models\PensionFundAdministrator;
use App\Models\SalaryStructureItem;
use App\Models\StatutoryContribution;
use App\Services\Payroll\StatutoryLines;
use App\Services\Payroll\StatutorySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Payroll > Statutory settings: the business's rates, bases and due dates
 * for PAYE, pension, NHF, NSITF and ITF; whether payroll works them out;
 * which allowances are pensionable; and its own PFAs.
 */
class StatutorySettingsController extends Controller
{
    public function edit()
    {
        $tenantId = auth()->user()->tenant_id;

        $allowanceNames = Allowance::pluck('name')
            ->merge(SalaryStructureItem::query()
                ->where('type', 'allowance')
                ->whereHas('salaryStructure', fn ($q) => $q->where('tenant_id', $tenantId))
                ->pluck('name'))
            ->map(fn ($n) => trim((string) $n))->filter()->unique()->sort()->values();

        return view('payroll.statutory.settings', [
            'contributions' => StatutoryContribution::forTenant($tenantId)->sortBy(fn ($c) => array_search($c->code, array_keys(StatutoryContribution::DEFAULTS), true)),
            'settings' => StatutorySettings::get($tenantId),
            'allowanceNames' => $allowanceNames,
            'ownPfas' => PensionFundAdministrator::where('tenant_id', $tenantId)->orderBy('name')->get(),
            'sharedPfas' => PensionFundAdministrator::whereNull('tenant_id')->where('is_active', true)->orderBy('name')->get(),
            'bases' => [
                StatutoryContribution::BASE_PENSIONABLE => 'Pensionable pay',
                StatutoryContribution::BASE_BASIC => 'Basic salary',
                StatutoryContribution::BASE_GROSS => 'Gross pay',
            ],
        ]);
    }

    public function update(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $codes = array_keys(StatutoryContribution::DEFAULTS);

        $validated = $request->validate([
            'auto' => ['nullable', 'boolean'],
            'pensionable_components' => ['nullable', 'string', 'max:255'],
            'contributions' => ['required', 'array'],
            'contributions.*.name' => ['required', 'string', 'max:100'],
            'contributions.*.rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'contributions.*.base' => ['nullable', Rule::in([StatutoryContribution::BASE_PENSIONABLE, StatutoryContribution::BASE_BASIC, StatutoryContribution::BASE_GROSS])],
            'contributions.*.is_enabled' => ['nullable', 'boolean'],
            'contributions.*.due_rule' => ['required', Rule::in(array_keys(StatutoryContribution::DUE_RULES))],
            'contributions.*.due_value' => ['nullable', 'integer', 'min:1', 'max:60'],
            'contributions.*.effective_from' => ['nullable', 'date'],
            'contributions.*.source' => ['nullable', 'string', 'max:2000'],
        ]);

        $errors = [];
        foreach ($validated['contributions'] as $code => $row) {
            if (! in_array($code, $codes, true)) {
                $errors["contributions.{$code}"] = 'Unknown scheme.';

                continue;
            }
            $value = $row['due_value'] ?? null;
            $max = match ($row['due_rule']) {
                StatutoryContribution::DUE_DAY_OF_NEXT_MONTH => 31,
                StatutoryContribution::DUE_NEXT_YEAR => 12,
                StatutoryContribution::DUE_WORKING_DAYS_AFTER_PAY => 60,
                default => null,
            };
            if ($max !== null && ($value === null || $value > $max)) {
                $errors["contributions.{$code}.due_value"] = "Enter a number from 1 to {$max} for this due date.";
            }
            if ($code !== StatutoryLines::PAYE && ($row['rate'] ?? null) === null) {
                $errors["contributions.{$code}.rate"] = 'Enter a rate.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($tenantId, $validated) {
            $rows = StatutoryContribution::forTenant($tenantId);
            foreach ($validated['contributions'] as $code => $row) {
                $rows[$code]->update([
                    'name' => $row['name'],
                    'rate' => $code === StatutoryLines::PAYE ? null : $row['rate'],
                    'base' => $code === StatutoryLines::PAYE ? null : ($row['base'] ?? $rows[$code]->base),
                    'is_enabled' => (bool) ($row['is_enabled'] ?? false),
                    'due_rule' => $row['due_rule'],
                    'due_value' => $row['due_value'] ?? null,
                    'effective_from' => $row['effective_from'] ?? null,
                    'source' => $row['source'] ?? null,
                ]);
            }

            StatutorySettings::put($tenantId, (bool) ($validated['auto'] ?? false),
                explode(',', (string) ($validated['pensionable_components'] ?? '')));
        });

        return redirect()->route('payroll.statutory.settings')->with('success', 'Statutory settings saved. They apply to payroll worked out from now on.');
    }

    public function storePfa(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('pension_fund_administrators', 'name')
                ->where(fn ($q) => $q->where(fn ($w) => $w->whereNull('tenant_id')->orWhere('tenant_id', $tenantId)))],
            'code' => ['nullable', 'string', 'max:30'],
        ]);

        PensionFundAdministrator::create($validated + ['tenant_id' => $tenantId, 'is_active' => true]);

        return redirect()->route('payroll.statutory.settings')->with('success', 'Pension Fund Administrator added.');
    }

    public function updatePfa(Request $request, PensionFundAdministrator $pfa)
    {
        $tenantId = auth()->user()->tenant_id;
        // Only the business's own PFAs; the PenCom list is shared.
        abort_unless($pfa->tenant_id === $tenantId, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('pension_fund_administrators', 'name')->ignore($pfa->id)
                ->where(fn ($q) => $q->where(fn ($w) => $w->whereNull('tenant_id')->orWhere('tenant_id', $tenantId)))],
            'code' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $pfa->update(['name' => $validated['name'], 'code' => $validated['code'] ?? null, 'is_active' => (bool) ($validated['is_active'] ?? false)]);

        return redirect()->route('payroll.statutory.settings')->with('success', 'Pension Fund Administrator updated.');
    }
}
