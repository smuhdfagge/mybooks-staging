<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create invoices');
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'discount_type' => ['nullable', 'in:percentage,fixed'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100', $this->configuredTaxRateRule()],
        ];
    }

    /**
     * Tax on each line must be 0, one of the organisation's active tax rates
     * or tax-group totals, or the line item's own rate (finding M4).
     * Organisations that have not set up any tax rates keep free entry.
     */
    private function configuredTaxRateRule(): \Closure
    {
        $tenantId = auth()->user()->tenant_id;

        $allowed = \App\Models\TaxRate::where('tenant_id', $tenantId)->where('is_active', true)->pluck('rate')
            ->map(fn ($rate) => (float) $rate)
            ->merge(\App\Models\TaxGroup::with('taxRates')->where('tenant_id', $tenantId)->where('is_active', true)->get()
                ->map(fn ($group) => (float) $group->combined_rate))
            ->unique()
            ->values();

        return function (string $attribute, $value, \Closure $fail) use ($allowed) {
            $rate = (float) $value;
            if ($allowed->isEmpty() || $rate == 0.0) {
                return;
            }

            $index = explode('.', $attribute)[1] ?? null;
            $itemId = $this->input("items.{$index}.item_id");
            $itemRate = $itemId ? \App\Models\Item::find($itemId)?->effective_tax_rate : null;

            foreach ($allowed->concat([(float) ($itemRate ?? 0)]) as $candidate) {
                if (abs($candidate - $rate) < 0.005) {
                    return;
                }
            }

            $fail('Use one of your tax rates ('.$allowed->filter()->map(fn ($r) => rtrim(rtrim(number_format($r, 2), '0'), '.').'%')->implode(', ').') or 0.');
        };
    }
}
