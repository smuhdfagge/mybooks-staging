<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Creating an account, from the web form or the API (finding Q5). The rules
 * already matched; both now take is_active (API only before), and an
 * account code already used in the business is refused here instead of
 * failing at the database.
 */
class StoreChartOfAccountRequest extends FormRequest
{
    public const TYPES = ['asset', 'liability', 'equity', 'income', 'expense'];

    public function authorize(): bool
    {
        return $this->user()->can('create chart-of-accounts');
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'account_code' => ['required', 'string', 'max:20', $this->uniqueCode($tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(self::TYPES)],
            'sub_type' => ['nullable', 'string', 'max:100'],
            'parent_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string'],
            'opening_balance' => ['nullable', 'numeric'],
            'is_active' => ['boolean'],
        ];
    }

    protected function uniqueCode(?int $tenantId): Unique
    {
        return Rule::unique('chart_of_accounts', 'account_code')->where('tenant_id', $tenantId);
    }
}
