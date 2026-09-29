<?php

namespace App\Http\Requests;

/**
 * Changing a bank account, from the web form or the API (finding Q5).
 * Header fields may be left out so the API can send only what changes.
 * The opening balance is set once, when the account is created.
 */
class UpdateBankRequest extends StoreBankRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit banks');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['name', 'account_type', 'currency'] as $field) {
            $rules[$field] = array_merge(['sometimes'], array_values(array_diff((array) $rules[$field], ['required'])));
        }
        unset($rules['opening_balance'], $rules['opening_balance_date']);

        return $rules;
    }
}
