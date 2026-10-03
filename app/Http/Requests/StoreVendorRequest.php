<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating a vendor, from the web form or the API (finding Q5).
 * The rules already matched; the API also took is_active, so both do now.
 */
class StoreVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create vendors');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'payment_terms' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            // Withholding tax: payee type sets the rate and the authority (NRS or state IRS).
            'payee_type' => ['sometimes', Rule::in(['company', 'individual'])],
            'wht_category_id' => ['nullable', Rule::exists('wht_categories', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'wht_exempt' => ['boolean'],
        ];
    }
}
