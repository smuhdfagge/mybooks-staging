<?php

namespace App\Http\Requests;

/**
 * Changing an invoice, from the web form or the API (finding Q5). Same
 * rules as creating one, but every header field may be left out, so the
 * API can send only what changes. The web form sends them all anyway.
 */
class UpdateInvoiceRequest extends StoreInvoiceRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit invoices');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['customer_id', 'invoice_date', 'due_date', 'items'] as $field) {
            $rules[$field] = array_merge(['sometimes'], array_values(array_diff((array) $rules[$field], ['required'])));
            if ($field === 'items') {
                $rules[$field][] = 'min:1';
            }
        }
        // A changed invoice keeps its status; payments and dates move it on.
        unset($rules['status'], $rules['sales_order_id']);

        return $rules;
    }
}
