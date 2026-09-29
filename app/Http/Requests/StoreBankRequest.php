<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating a bank account, from the web form or the API (finding Q5).
 * Currency is a 3-letter code (the API took up to 10 characters, the column
 * holds 3). Bank name and account number are optional, as on the web, since
 * a cash account has neither (the API required them).
 */
class StoreBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create banks');
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'account_type' => ['required', 'in:checking,savings,credit_card,cash,other'],
            'currency' => ['required', 'string', 'size:3'],
            'routing_number' => ['nullable', 'string', 'max:50'],
            'swift_code' => ['nullable', 'string', 'max:20'],
            'iban' => ['nullable', 'string', 'max:50'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'branch_address' => ['nullable', 'string', 'max:500'],
            'opening_balance' => ['nullable', 'numeric'],
            'opening_balance_date' => ['nullable', 'date'],
            'chart_of_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string'],
            'is_primary' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
