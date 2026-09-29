<?php

namespace App\Http\Requests;

use App\Models\TaxRate;
use Illuminate\Validation\Rules\Unique;

/**
 * Changing a tax rate, from the web form or the API (finding Q5). Same
 * rules as creating one, but header fields may be left out so the API can
 * send only what changes. The web form sends them all.
 */
class UpdateTaxRateRequest extends StoreTaxRateRequest
{
    public function authorize(): bool
    {
        // The web and API routes guard tax rates with different permissions.
        return $this->user()->can($this->routeIs('api.*') ? 'edit chart-of-accounts' : 'edit tax-rates');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['name', 'rate', 'type', 'applies_to'] as $field) {
            $rules[$field] = array_merge(['sometimes'], array_values(array_diff((array) $rules[$field], ['required'])));
        }

        return $rules;
    }

    protected function uniqueCode(?int $tenantId): Unique
    {
        $taxRate = $this->route('taxRate');

        return parent::uniqueCode($tenantId)->ignore($taxRate instanceof TaxRate ? $taxRate->id : null);
    }
}
