<?php

namespace App\Http\Requests;

use App\Models\TaxRate;
use App\Services\Accounting\VatTreatment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Creating a tax rate, from the web form or the API (finding Q5). Name is
 * at most 100 characters (the web took 255). Type is required and code,
 * type and tax number are now taken by the API too (they were web only).
 * A code must be unique in the business, checked here for both.
 */
class StoreTaxRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The web and API routes guard tax rates with different permissions.
        return $this->user()->can($this->routeIs('api.*') ? 'edit chart-of-accounts' : 'create tax-rates');
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20', $this->uniqueCode($tenantId)],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'type' => ['required', 'in:inclusive,exclusive'],
            'applies_to' => ['required', 'in:sales,purchases,both'],
            // For the VAT return. A rate above 0% is always standard-rated.
            'vat_treatment' => ['nullable', 'in:'.implode(',', VatTreatment::ALL)],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_compound' => ['boolean'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * 0% can't be "standard-rated" (that would hide VAT not charged), and a
     * rate that charges VAT can't be zero-rated or exempt.
     */
    public function after(): array
    {
        return [function ($validator) {
            $treatment = $this->input('vat_treatment');
            if ($treatment === null || $treatment === '' || $validator->errors()->has('rate')) {
                return;
            }
            $current = $this->route('taxRate');
            $rate = (float) ($this->input('rate') ?? ($current instanceof TaxRate ? $current->rate : 0));
            if ($rate > 0 && $treatment !== VatTreatment::STANDARD) {
                $validator->errors()->add('vat_treatment', 'A rate that charges VAT is standard-rated. Use 0% for zero-rated, exempt or out-of-scope supplies.');
            }
            if ($rate <= 0 && $treatment === VatTreatment::STANDARD) {
                $validator->errors()->add('vat_treatment', 'A 0% rate is zero-rated, exempt or out of scope, not standard-rated.');
            }
        }];
    }

    public function messages(): array
    {
        return ['code.unique' => 'A tax rate with this code already exists.'];
    }

    protected function uniqueCode(?int $tenantId): Unique
    {
        return Rule::unique('tax_rates', 'code')->where('tenant_id', $tenantId)->withoutTrashed();
    }
}
