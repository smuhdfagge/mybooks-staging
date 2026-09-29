<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Recording an expense, from the web form or the API (finding Q5). The
 * amount must be above zero (the API took 0). A new expense always starts
 * as a draft, as on the web; the API could create one already awaiting
 * approval. Tax, payment method, customer and notes were API only.
 */
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
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'vendor_id' => ['nullable', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'paid_through_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('tenant_id', $tenantId)],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_billable' => ['boolean'],
            // Submitting, approval and payment are their own steps (I4).
            'status' => ['sometimes', Rule::in([Expense::STATUS_DRAFT])],
        ];
    }
}
