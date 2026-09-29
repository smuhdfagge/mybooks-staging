<?php

namespace App\Http\Requests;

/**
 * Changing a bill, from the web form or the API (finding Q5). Same rules as
 * creating one; header fields may be left out so the API can send only what
 * changes. A bill keeps its status and purchase order.
 */
class UpdateBillRequest extends StoreBillRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit bills');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['vendor_id', 'bill_date', 'due_date', 'items'] as $field) {
            $rules[$field] = array_merge(['sometimes'], array_values(array_diff((array) $rules[$field], ['required'])));
        }
        unset($rules['status'], $rules['purchase_order_id']);

        return $rules;
    }
}
