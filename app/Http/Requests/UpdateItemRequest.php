<?php

namespace App\Http\Requests;

/**
 * Changing an item, from the web form or the API (finding Q5). Same rules
 * as creating one, but name, type and selling price may be left out so the
 * API can send only what changes. The web form sends them anyway.
 */
class UpdateItemRequest extends StoreItemRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit items');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['name', 'type', 'selling_price'] as $field) {
            $rules[$field] = array_merge(['sometimes'], array_values(array_diff((array) $rules[$field], ['required'])));
        }

        return $rules;
    }
}
