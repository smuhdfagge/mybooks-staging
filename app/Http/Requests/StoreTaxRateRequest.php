<?php

namespace App\Http\Requests;

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
            'tax_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_compound' => ['boolean'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
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
