<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentMadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create payments-made');
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'vendor_id' => ['required', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'bill_id' => ['nullable', Rule::exists('bills', 'id')->where('tenant_id', $tenantId)],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:50'],
            'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('tenant_id', $tenantId)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
