<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating or changing a cash sale (finding Q5). Line rules are the
 * invoice's, so VAT rates and discounts are checked the same way.
 */
class StoreSalesReceiptRequest extends FormRequest
{
    use Concerns\ValidatesSalesLines;

    public function authorize(): bool
    {
        return $this->user()->can($this->isMethod('post') ? 'create sales-receipts' : 'edit sales-receipts');
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'receipt_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            ...$this->salesLineRules($tenantId),
        ];
    }
}
