<?php

namespace App\Http\Requests;

use App\Models\ChartOfAccount;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Changing an account, from the web form or the API (finding Q5). Header
 * fields may be left out so the API can send only what changes. The type
 * may change (the web allowed it, the API did not) only while no journal
 * lines use the account. The opening balance is set once, at creation.
 */
class UpdateChartOfAccountRequest extends StoreChartOfAccountRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit chart-of-accounts');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['account_code', 'name', 'type'] as $field) {
            $rules[$field] = array_merge(['sometimes'], array_values(array_diff((array) $rules[$field], ['required'])));
        }
        unset($rules['opening_balance']);

        $account = $this->account();
        if ($account && $account->journalEntries()->exists()) {
            $rules['type'][] = function (string $attribute, mixed $value, \Closure $fail) use ($account) {
                if ($value !== $account->type) {
                    $fail('The type of an account with journal lines cannot be changed.');
                }
            };
        }
        if ($account) {
            $rules['parent_id'][] = Rule::notIn([$account->id]);
        }

        return $rules;
    }

    protected function uniqueCode(?int $tenantId): Unique
    {
        return parent::uniqueCode($tenantId)->ignore($this->account()?->id);
    }

    private function account(): ?ChartOfAccount
    {
        $account = $this->route('chartOfAccount');

        return $account instanceof ChartOfAccount ? $account : null;
    }
}
