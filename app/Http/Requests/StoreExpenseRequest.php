<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create expenses');
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'expense_date' => ['required', 'date'],
            'expense_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'vendor_id' => ['nullable', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'paid_through_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('tenant_id', $tenantId)],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_billable' => ['boolean'],
        ];
    }
}
