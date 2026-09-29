<?php

namespace App\Http\Requests;

/**
 * Changing a vendor, from the web form or the API (finding Q5). Same
 * rules as creating one, but the name may be left out so the API can send
 * only what changes. The web form sends it anyway.
 */
class UpdateVendorRequest extends StoreVendorRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit vendors');
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['name'] = ['sometimes', 'string', 'max:255'];

        return $rules;
    }
}
