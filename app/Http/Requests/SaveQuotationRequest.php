<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating or changing a quotation. Line rules are the invoice's
 * (ValidatesSalesLines), so tax rates and discounts are checked the same way.
 */
class SaveQuotationRequest extends FormRequest
{
    use Concerns\ValidatesSalesLines;

    public function authorize(): bool
    {
        return $this->user()->can($this->isMethod('post') ? 'create invoices' : 'edit invoices');
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'quotation_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:quotation_date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            ...$this->salesLineRules($tenantId),
        ];
    }
}
