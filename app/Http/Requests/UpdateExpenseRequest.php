<?php

namespace App\Http\Requests;

/**
 * Changing an expense, from the web form or the API (finding Q5). Same
 * rules as recording one, but header fields may be left out so the API can
 * send only what changes. Status moves only through submit/approve/reject.
 */
class UpdateExpenseRequest extends StoreExpenseRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit expenses');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['name', 'expense_date', 'expense_account_id', 'amount'] as $field) {
            $rules[$field] = array_merge(['sometimes'], array_values(array_diff((array) $rules[$field], ['required'])));
        }
        unset($rules['status']);

        return $rules;
    }
}
