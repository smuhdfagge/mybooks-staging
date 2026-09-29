<?php

namespace App\Http\Requests;

/**
 * Changing a manual journal, from the web form or the API (finding Q5).
 * Same rules as creating one, but header fields and the lines may be left
 * out so the API can send only what changes. The web form sends them all.
 */
class UpdateJournalRequest extends StoreJournalRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit journals');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['journal_date', 'description', 'entries'] as $field) {
            $rules[$field] = array_merge(['sometimes'], array_values(array_diff((array) $rules[$field], ['required'])));
        }

        return $rules;
    }
}
