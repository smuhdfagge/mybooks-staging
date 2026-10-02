<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentReceivedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create payments-received');
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'invoice_id' => ['nullable', Rule::exists('invoices', 'id')->where('tenant_id', $tenantId)],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:50'],
            'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('tenant_id', $tenantId)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_deposit' => ['boolean'],
            'apply_deposit_id' => ['nullable', Rule::exists('payments_received', 'id')->where('tenant_id', $tenantId)],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            // Withholding tax the customer deducted (amount is the money received).
            'wht_category_id' => ['nullable', Rule::exists('wht_categories', 'id')->where('tenant_id', $tenantId)],
            'wht_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
